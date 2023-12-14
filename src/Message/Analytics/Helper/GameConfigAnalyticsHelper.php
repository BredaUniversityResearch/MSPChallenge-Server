<?php

namespace App\Message\Analytics\Helper;

use JsonSerializable;

class GameConfigAnalyticsHelper implements JsonSerializable
{

    public string $fileName;
    public string $version;
    public string $versionMessage;
    public string $region;
    public string $description;

    public function __construct(
        string $fileName,
        string $version,
        string $versionMessage,
        string $region,
        string $description
    ) {
        $this->fileName = $fileName;
        $this->version = $version;
        $this->versionMessage = $versionMessage;
        $this->region = $region;
        $this->description = $description;
    }

    public function jsonSerialize() : array
    {
        return[
          'fileName' => $this->fileName,
          'version' => $this->version,
          'versionMessage' => $this->versionMessage,
          'region' => $this->region,
          'description' => $this->description
        ];
    }
}
