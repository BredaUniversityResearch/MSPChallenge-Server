<?php

namespace App\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\Migrations\Event\MigrationsVersionEventArgs;
use Doctrine\Migrations\Events;

class DoctrineMigrationProgressSubscriber implements EventSubscriber
{
    /**
     * @return string[]
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::onMigrationsVersionExecuting,
            Events::onMigrationsVersionExecuted,
            Events::onMigrationsVersionSkipped,
        ];
    }

    public function onMigrationsVersionExecuting(MigrationsVersionEventArgs $args): void
    {
        $this->writeProgress('MIGRATION START', $args);
    }

    public function onMigrationsVersionExecuted(MigrationsVersionEventArgs $args): void
    {
        $this->writeProgress('MIGRATION DONE', $args);
    }

    public function onMigrationsVersionSkipped(MigrationsVersionEventArgs $args): void
    {
        $this->writeProgress('MIGRATION SKIP', $args);
    }

    private function writeProgress(string $state, MigrationsVersionEventArgs $args): void
    {
        $databaseName = $args->getConnection()->getParams()['dbname'] ?? '<unknown-db>';
        $version = (string) $args->getPlan()->getVersion();

        fwrite(STDERR, sprintf('[%s] %s on %s%s', $state, $version, $databaseName, PHP_EOL));
    }
}
