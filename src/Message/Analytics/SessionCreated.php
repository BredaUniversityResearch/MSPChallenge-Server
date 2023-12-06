<?php

namespace App\Message\Analytics;

use DateTimeImmutable;
use JsonSerializable;

class SessionCreated extends AnalyticsMessageBase implements JsonSerializable
{

    public readonly int $id;
    public readonly string $name;
    public readonly string $game_config_filename;
    public readonly string $game_config_filepath;
    public readonly string $game_config_version;
    public readonly string $game_config_version_message;
    public readonly string $game_config_visibility;
    public readonly ?DateTimeImmutable $game_config_upload_time;
    public readonly string $game_config_upload_user; //TODO: check what data should be recorded for the user.
    public readonly string $game_config_region;
    public readonly string $game_config_description;
    public readonly DateTimeImmutable $game_creation_time;
    public readonly DateTimeImmutable $game_running_till_time;
    public readonly int $game_start_year;
    public readonly int $game_end_month;
    public readonly int $game_current_month;
    public readonly string $game_visibility;

    public function __construct(
        DateTimeImmutable $timeStamp,
        int $id,
        string $name,
        string $config_fileName,
        string $config_filePath,
        string $config_version,
        string $config_versionMessage,
        string $config_visibility,
        DateTimeImmutable $config_uploadTime,
        string $config_region,
        string $config_description,
        DateTimeImmutable $game_creationTime,
        DateTimeImmutable $game_runningTillTime,
        int $game_startYear,
        int $game_endMonth,
        int $game_currentMonth,
        string $game_visibility
    ) {
        parent::__construct($timeStamp);
        $this->id = $id;
        $this->name = $name;
        $this->game_config_filename = $config_fileName;
        $this->game_config_filepath = $config_filePath;
        $this->game_config_version = $config_version;
        $this->game_config_version_message = $config_versionMessage;
        $this->game_config_visibility = $config_visibility;
        $this->game_config_upload_time = $config_uploadTime;
        $this->game_config_region = $config_region;
        $this->game_config_description = $config_description;
        $this->game_creation_time = $game_creationTime;
        $this->game_running_till_time = $game_runningTillTime;
        $this->game_start_year = $game_startYear;
        $this->game_end_month = $game_endMonth;
        $this->game_current_month = $game_currentMonth;
        $this->game_visibility = $game_visibility;
    }

    public function JsonSerialize() : array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'game_config_filename' => $this->game_config_filename,
            'game_config_filepath' => $this->game_config_filepath,
            'game_config_version' => $this->game_config_version,
            'game_config_version_message' => $this->game_config_version_message,
            'game_config_visibility' => $this->game_config_visibility,
            'game_config_upload_time' => $this->game_config_upload_time->format('c'),
            'game_config_region' => $this->game_config_region,
            'game_config_description' => $this->game_config_description,
            'game_creation_time' => $this->game_creation_time->format('c'),
            'game_running_till_time' => $this->game_running_till_time->format('c'),
            'game_start_year' => $this->game_start_year,
            'game_end_month' => $this->game_end_month,
            'game_current_month' => $this->game_current_month,
            'game_visibility' => $this->game_visibility
        ];
    }

}
