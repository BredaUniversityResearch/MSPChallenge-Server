<?php
namespace App\Tests\ServerManager;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class SetupBeforeTests extends KernelTestCase
{

    /**
     * Checks whether ServerManager test database exists
     * If not, runs completeCleanInstallDatabases
     * If so, we just assume it's been migrated and has fixtures
     * @return void
     */
    public static function ensureDatabaseIsReady(): void
    {
        $app = new Application(static::bootKernel());
        $input = new ArrayInput([
            'command' => 'doctrine:database:create',
            '--connection' => $_ENV['DBNAME_SERVER_MANAGER']
        ]);
        $input->setInteractive(false);
        $statusCode = $app->doRun($input, new NullOutput());
        if ($statusCode === 0) {
            self::completeCleanInstallDatabases();
        }
    }
    
    /**
     * Completely removes, creates, migrates and adds fixtures to the test databases
     * @return void
     */
    public static function completeCleanInstallDatabases(): void
    {
        $app = new Application(static::bootKernel());
        $input = new ArrayInput([
            'command' => 'doctrine:database:drop',
            '--connection' => $_ENV['DBNAME_SERVER_MANAGER'],
            '--if-exists' => true,
            '--force' => true,
            '--no-interaction' => true,
        ]);
        $input->setInteractive(false);
        $app->doRun($input, new NullOutput());

        $input = new ArrayInput([
            'command' => 'doctrine:database:drop',
            '--connection' => 'msp_session_1', // don't worry, only removes msp_session_1_test database
            '--if-exists' => true,
            '--force' => true,
            '--no-interaction' => true,
        ]);
        $input->setInteractive(false);
        $app->doRun($input, new NullOutput());

        $input = new ArrayInput([
            'command' => 'doctrine:database:drop',
            '--connection' => 'msp_session_2', // don't worry, only removes msp_session_2_test database
            '--if-exists' => true,
            '--force' => true,
            '--no-interaction' => true,
        ]);
        $input->setInteractive(false);
        $app->doRun($input, new NullOutput());

        $input2 = new ArrayInput([
            'command' => 'doctrine:database:create',
            '--connection' => $_ENV['DBNAME_SERVER_MANAGER']
        ]);
        $input2->setInteractive(false);
        $app->doRun($input2, new NullOutput());

        $input3 = new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            '--em' => $_ENV['DBNAME_SERVER_MANAGER'],
        ]);
        $input3->setInteractive(false);
        $app->doRun($input3, new NullOutput());

        $input4 = new ArrayInput([
            'command' => 'doctrine:fixtures:load',
            '--em' => $_ENV['DBNAME_SERVER_MANAGER'],
            '--append' => true
        ]);
        $input4->setInteractive(false);
        $app->doRun($input4, new NullOutput());
    }

}
