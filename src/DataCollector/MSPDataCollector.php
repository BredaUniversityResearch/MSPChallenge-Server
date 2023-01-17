<?php

namespace App\DataCollector;

use Symfony\Bundle\FrameworkBundle\DataCollector\AbstractDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MSPDataCollector extends AbstractDataCollector
{
    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        $address = \ServerManager\ServerManager::getInstance()->GetTranslatedServerURL();
        if ($address == "localhost") {
            $address .= PHP_EOL . "Translated automatically to ".gethostbyname(gethostname());
        }

        $this->data = [
            'MSP Challenge Server version' => \ServerManager\ServerManager::getInstance()->GetCurrentVersion(),
            'Server Address' => $address,
            // e.g.:
            //'currentToken' => \ServerManager\Session::get('currentToken'),
        ];
    }

    public function getData(): array
    {
        return $this->data;
    }
}
