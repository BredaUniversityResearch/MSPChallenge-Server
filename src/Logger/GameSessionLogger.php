<?php
namespace App\Logger;

use Monolog\Handler\AbstractProcessingHandler;

class GameSessionLogger extends AbstractProcessingHandler
{
    public function __construct(
        private readonly string $kernelLogsDir,
        private readonly string $kernelProjectDir
    ) {
        parent::__construct();
    }

    protected function write(array $record): void
    {
        $path = ($_ENV['APP_ENV'] !== 'test') ?
            $this->kernelProjectDir.'/ServerManager/log/' :
            $this->kernelLogsDir.'/';

        // hack to make the placeholders work (not sure why monolog fails to do this)
        foreach ($record['context'] as $key => $val) {
            $record['formatted'] = str_replace('{'.$key.'}', $val, $record['formatted']);
        }

        error_log(
            $record['formatted'],
            3,
            $path.'log_session_'.$record['context']['gameSession'].'.log'
        );
    }
}
