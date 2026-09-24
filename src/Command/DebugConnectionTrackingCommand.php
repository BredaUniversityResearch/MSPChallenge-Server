<?php

namespace App\Command;

use App\Domain\Services\ConnectionManager;
use App\Domain\Services\ProcessNameDetector;
use App\EventSubscriber\MspTrackerExtensionBootstrapSubscriber;
use App\Message\ConnectionTracking\LowLevelConnectionTrackedMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'debug:connection-tracking',
    description: 'Diagnoses the msp_tracker connection-tracking pipeline end-to-end.',
)]
class DebugConnectionTrackingCommand extends Command
{
    public function __construct(
        private readonly MspTrackerExtensionBootstrapSubscriber $subscriber,
        private readonly ConnectionManager $connectionManager,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'fire-test-event',
            null,
            InputOption::VALUE_NONE,
            'Dispatch a synthetic test event through the full pipeline and verify the DB row.',
        );
        $this->addOption(
            'test-subscriber-pdo',
            null,
            InputOption::VALUE_NONE,
            'Open a fresh raw PDO connection after subscriber registration and verify it is tracked.',
        );
    }

    // phpcs:disable Generic.Files.LineLength
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('MSP Tracker – connection-tracking diagnostics');

        $ok = true;

        // ── Step 1: C extension ──────────────────────────────────────────────
        $io->section('Step 1 – C extension (msp_tracker)');
        $extLoaded = extension_loaded('msp_tracker');
        $fnExists  = function_exists('msp_tracker_register_connection_callback');

        $io->definitionList(
            ['extension_loaded("msp_tracker")'              => $extLoaded ? '<info>true</info>' : '<error>FALSE</error>'],
            ['fn: msp_tracker_register_connection_callback' => $fnExists ? '<info>exists</info>' : '<comment>missing</comment>'],
            ['fn: msp_tracker_emit_connection_event'        => function_exists('msp_tracker_emit_connection_event') ? '<info>exists</info>' : '<comment>missing</comment>'],
            ['fn: msp_tracker_set_enabled'                  => function_exists('msp_tracker_set_enabled') ? '<info>exists</info>' : '<comment>missing</comment>'],
            ['fn: msp_tracker_is_enabled'                   => function_exists('msp_tracker_is_enabled') ? '<info>exists</info>' : '<comment>missing</comment>'],
        );

        if (!$extLoaded) {
            $io->warning(
                "The msp_tracker C extension is NOT loaded.\n" .
                "PDO connections cannot be intercepted automatically.\n" .
                "Only manually dispatched LowLevelConnectionTrackedMessages will be written.\n\n" .
                "To fix:\n" .
                "  cd php-ext/msp_tracker && phpize && ./configure && make && make install\n" .
                "  Add 'extension=msp_tracker' to the active php.ini\n" .
                "  Verify which php.ini supervisor uses: php --ini"
            );
            $ok = false;
        }

        // ── Step 2: env vars ─────────────────────────────────────────────────
        $io->section('Step 2 – Environment variables');
        $trackingEnabled = filter_var(
            $_ENV['DATABASE_CONNECTION_TRACKING_ENABLED'] ?? '1',
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        ) ?? true;

        $io->definitionList(
            ['DATABASE_CONNECTION_TRACKING_ENABLED' => $trackingEnabled ? '<info>enabled</info>' : '<error>DISABLED</error>'],
            ['DBNAME_DEFAULT'        => $_ENV['DBNAME_DEFAULT'] ?? '<comment>(not set)</comment>'],
            ['DBNAME_SERVER_MANAGER' => $_ENV['DBNAME_SERVER_MANAGER'] ?? '<comment>(not set)</comment>'],
            ['DB_PROCESS_NAME'       => $_ENV['DB_PROCESS_NAME'] ?? '<comment>(not set – will be auto-detected)</comment>'],
        );

        if (!$trackingEnabled) {
            $io->warning('DATABASE_CONNECTION_TRACKING_ENABLED is falsy – no tracking will happen.');
            $ok = false;
        }

        // ── Step 3: process-name detection ───────────────────────────────────
        $io->section('Step 3 – Process-name detection');
        $processName = ProcessNameDetector::getProcessName();
        $nullNote    = '<comment>NULL (async Drift tracking skipped by TrackingMysqlDriver)</comment>';
        $io->definitionList(['detected process name' => $processName ?? $nullNote]);
        $io->writeln('Raw inputs: ' . json_encode(ProcessNameDetector::collectProcessNameInputs(), JSON_PRETTY_PRINT));

        // ── Step 4: callback registration ────────────────────────────────────
        $io->section('Step 4 – Callback registration');
        if ($fnExists) {
            $this->subscriber->registerCallbackIfAvailable('debug:connection-tracking');
            $io->success('msp_tracker_register_connection_callback() called (check debug logs for result).');
        } else {
            $io->warning('Skipped – extension not loaded (or stub returns false).');
        }

        // ── Step 5: msp_tracker.connection table ─────────────────────────────
        $io->section('Step 5 – msp_tracker.connection table accessibility');
        try {
            $conn  = $this->connectionManager->createServerManagerConnection();
            $count = (int) $conn->executeQuery('SELECT COUNT(*) FROM `msp_tracker`.`connection`')->fetchOne();
            $conn->close();
            $io->success("msp_tracker.connection is accessible. Current row count: {$count}");
        } catch (\Throwable $e) {
            $io->error("Cannot query msp_tracker.connection: {$e->getMessage()}");
            $ok = false;
        }

        // ── Step 6: messenger_connection_tracking queue depth ─────────────────
        $io->section('Step 6 – messenger_connection_tracking queue depth');
        try {
            $conn    = $this->connectionManager->createServerManagerConnection();
            $pending = (int) $conn->executeQuery(
                "SELECT COUNT(*) FROM `messenger_connection_tracking` WHERE delivered_at IS NULL"
            )->fetchOne();
            $conn->close();
            if ($pending > 0) {
                $io->warning(
                    "{$pending} message(s) queued but not yet consumed.\n" .
                    "Is 'messenger-connection-tracking' supervisor process running?\n" .
                    "Check: supervisorctl status messenger-connection-tracking"
                );
            } else {
                $io->success("Queue is empty (messages are being consumed promptly).");
            }
        } catch (\Throwable $e) {
            $io->warning(
                "Cannot query messenger_connection_tracking: {$e->getMessage()}\n" .
                "(Table may not exist yet – start the worker so it auto-creates it.)"
            );
        }

        // ── Step 7: optional synthetic end-to-end test ───────────────────────
        if ($input->getOption('fire-test-event')) {
            $io->section('Step 7 – Synthetic end-to-end test (--fire-test-event)');

            $syntheticConnectionId = PHP_INT_MAX - random_int(0, 999999);
            $syntheticProcessName  = 'debug-connection-tracking-test';
            $dbName                = $_ENV['DBNAME_SERVER_MANAGER'] ?? 'msp_server_manager';

            if (function_exists('msp_tracker_emit_connection_event')) {
                // Goes through the registered callback → bus → worker → DB (tests the full C-extension path)
                $fired = msp_tracker_emit_connection_event([
                    'event'         => 'debug_synthetic',
                    'connection_id' => $syntheticConnectionId,
                    'process_name'  => $syntheticProcessName,
                    'db_name'       => $dbName,
                    'user'          => 'debug-test',
                ]);
                if ($fired) {
                    $io->writeln('<info>msp_tracker_emit_connection_event() dispatched the test payload.</info>');
                } else {
                    $io->writeln(
                        '<comment>msp_tracker_emit_connection_event() returned false'
                        . ' – callback not registered or tracking disabled.</comment>'
                    );
                }
            } else {
                // Fallback: bypass C path
                $this->messageBus->dispatch(new LowLevelConnectionTrackedMessage([
                    'event'         => 'debug_synthetic',
                    'connection_id' => $syntheticConnectionId,
                    'process_name'  => $syntheticProcessName,
                    'db_name'       => $dbName,
                    'user'          => 'debug-test',
                ]));
                $io->writeln(
                    '<comment>Extension unavailable – dispatched directly to bus'
                    . ' (bypasses C interception path).</comment>'
                );
            }

            $io->writeln('Waiting 3 s for the worker to consume the message…');
            sleep(3);

            try {
                $conn  = $this->connectionManager->createServerManagerConnection();
                $found = (int) $conn->executeQuery(
                    'SELECT COUNT(*) FROM `msp_tracker`.`connection` WHERE connection_id = ?',
                    [$syntheticConnectionId]
                )->fetchOne();
                $conn->close();

                if ($found > 0) {
                    $io->success(
                        "End-to-end test PASSED – "
                        . "row for connection_id={$syntheticConnectionId} found in msp_tracker.connection."
                    );
                } else {
                    $io->error(
                        "End-to-end test FAILED – no row after 3 s.\n" .
                        "Either the worker is not running or the handler threw (check its log)."
                    );
                    $ok = false;
                }
            } catch (\Throwable $e) {
                $io->error("Could not verify test row: {$e->getMessage()}");
                $ok = false;
            }
        }

        // ── Step 8: verify subscriber with real PDO open ───────────────────
        if ($input->getOption('test-subscriber-pdo')) {
            $io->section('Step 8 – Subscriber + PDO interception test (--test-subscriber-pdo)');

            if (!extension_loaded('msp_tracker') || !function_exists('msp_tracker_register_connection_callback')) {
                $io->warning('Skipped: msp_tracker extension/callback API unavailable in this PHP runtime.');
            } else {
                // Re-arm the callback right before opening PDO to validate subscriber wiring.
                $this->subscriber->registerCallbackIfAvailable('debug:subscriber-pdo-test');

                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    $_ENV['DATABASE_HOST'] ?? 'localhost',
                    $_ENV['DATABASE_PORT'] ?? '3306',
                    $_ENV['DBNAME_SERVER_MANAGER'] ?? 'msp_server_manager',
                    $_ENV['DATABASE_CHARSET'] ?? 'utf8mb4',
                );

                $pdoUser = (string) ($_ENV['DATABASE_USER'] ?? 'root');
                $pdoPass = (string) ($_ENV['DATABASE_PASSWORD'] ?? '');

                try {
                    $pdo = new \PDO($dsn, $pdoUser, $pdoPass, [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    ]);
                    $connectionId = (int) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
                    $pdo = null;

                    if ($connectionId <= 0) {
                        $io->warning('Could not resolve CONNECTION_ID() for the raw PDO probe.');
                        $ok = false;
                    } else {
                        $io->writeln("Opened probe PDO connection_id={$connectionId}; waiting 3 s for async handling…");
                        sleep(3);

                        $conn = $this->connectionManager->createServerManagerConnection();
                        $found = (int) $conn->executeQuery(
                            'SELECT COUNT(*) FROM `msp_tracker`.`connection` WHERE connection_id = ?',
                            [$connectionId]
                        )->fetchOne();
                        $conn->close();

                        if ($found > 0) {
                            $io->success('Subscriber/PDO test PASSED: raw PDO open was tracked via callback path.');
                        } else {
                            $io->error(
                                'Subscriber/PDO test FAILED: no tracked row for probe CONNECTION_ID().' . "\n"
                                . 'This indicates callback timing/order issues for real PDO opens in this runtime.'
                            );
                            $ok = false;
                        }
                    }
                } catch (\Throwable $e) {
                    $io->error('Subscriber/PDO test failed to open probe PDO: ' . $e->getMessage());
                    $ok = false;
                }
            }
        }

        // ── Summary ──────────────────────────────────────────────────────────
        $io->section('Summary');
        if (!$extLoaded) {
            $io->listing([
                '<error>CRITICAL</error> msp_tracker C extension not loaded → PDO connections invisible to tracking.',
                'Run inside the container: php -m | grep msp_tracker',
                'Run: php --ini   (to find which php.ini supervisor uses)',
            ]);
        }
        if ($ok) {
            $io->success('All preconditions healthy. If tracking still fails, re-run with --fire-test-event.');
        } else {
            $io->warning('One or more checks failed – see above.');
        }

        return $ok ? Command::SUCCESS : Command::FAILURE;
    }
    // phpcs:enable Generic.Files.LineLength
}
