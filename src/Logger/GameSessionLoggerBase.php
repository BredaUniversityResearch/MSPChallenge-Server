<?php
namespace App\Logger;

use Monolog\Handler\AbstractProcessingHandler;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Filesystem\Filesystem;

abstract class GameSessionLoggerBase extends AbstractProcessingHandler
{
    public function __construct(
        private readonly ContainerBagInterface $params
    ) {
        parent::__construct();
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function handleWriteByArray(array $record): void
    {
        // hack to make the placeholders work (not sure why monolog fails to do this)
        foreach ($record['context'] as $key => $val) {
            $record['formatted'] = str_replace('{'.$key.'}', $val, $record['formatted']);
        }
        error_log($record['formatted'], 3, $this->getLogFilePath($record['context']['gameSession']));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function empty(int $gameListId): void
    {
        $fileSystem = new Filesystem();
        $fileSystem->remove($this->getLogFilePath($gameListId));
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function getLogFilePath(int $gameListId): string
    {
        return $this->params->get('app.server_manager_log_dir').
            sprintf($this->params->get('app.server_manager_log_name'), $gameListId);
    }
}
