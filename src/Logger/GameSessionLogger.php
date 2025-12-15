<?php
namespace App\Logger;

use Monolog\Handler\AbstractProcessingHandler;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Monolog\LogRecord;

class GameSessionLogger extends AbstractProcessingHandler
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

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function write(LogRecord $record): void
    {
        $arr = $record->toArray();
        // hack to make the placeholders work (not sure why monolog fails to do this)
        foreach ($arr['context'] as $key => $val) {
            if (!is_string($key) || !is_string($val)) {
                continue;
            }
            $arr['message'] = str_replace('{'.$key.'}', $val, $arr['message']);
        }
        error_log(
            json_encode($arr).PHP_EOL,
            3,
            $this->getLogFilePath($arr['context']['gameSession'])
        );
    }
}
