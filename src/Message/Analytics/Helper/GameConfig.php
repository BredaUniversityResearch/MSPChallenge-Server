<?php

namespace App\Message\Analytics\Helper;

use DateTimeImmutable;
use JsonSerializable;

class GameConfig implements JsonSerializable
{

    public string $fileName;
    public string $filePath;
    public string $version;
    public string $versionMessage;
    public string $visibility;
    public string $region;
    public string $description;
    public string $uploadUserName;
    public int $uploadUserAccountId;
    public DateTimeImmutable $uploadTime;

    public function __construct(
        string $fileName,
        string $filePath,
        string $version,
        string $versionMessage,
        string $visibility,
        string $region,
        string $description,
        string $uploadUserName,
        int $uploadUserAccountId,
        DateTimeImmutable $uploadTime
    ) {
        $this->fileName = $fileName;
        $this->filePath = $filePath;
        $this->version = $version;
        $this->versionMessage = $versionMessage;
        $this->visibility = $visibility;
        $this->region = $region;
        $this->description = $description;
        $this->uploadUserName = $uploadUserName;
        $this->uploadUserAccountId = $uploadUserAccountId;
        $this->uploadTime = $uploadTime;
    }

    //phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function JsonSerialize() : array
    {
        return[
          'fileName' => $this->fileName,
          'filePath' => $this->filePath,
          'version' => $this->version,
          'versionMessage' => $this->versionMessage,
          'visibility' => $this->visibility,
          'region' => $this->region,
          'description' => $this->description,
          'uploadUserName' => $this->uploadUserName,
          'uploadUserAccountId' => $this->uploadUserAccountId,
          'uploadTime' => $this->uploadTime->format('c')
        ];
    }
}
