<?php

namespace App\Domain\WsServer;

interface MeasurementCollectionManagerInterface
{
    public function addToMeasurementCollection(string $name, string $measurementId, float $measurementTime);
}
