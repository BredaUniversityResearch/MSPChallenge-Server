<?php

namespace App\Domain\WsServer;

interface MeasurementCollectionManagerInterface
{
    public function startMeasurementCollection(string $name);
    public function addToMeasurementCollection(string $name, string $measurementId, float $measurementTime);
    public function endMeasurementCollection(string $name);
}
