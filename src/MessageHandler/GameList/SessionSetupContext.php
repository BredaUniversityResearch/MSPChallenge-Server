<?php

namespace App\MessageHandler\GameList;

use App\Entity\Country;
use App\Entity\Geometry;
use App\Entity\Grid;
use App\Entity\Layer;

class SessionSetupContext
{
    /**
     * @var array<int, Country> // countryId, Country
     */
    private array $countries = [];

    /**
     * @var array<string, Layer> // layerName, Layer
     */
    private array $layers = [];

    /**
     * @var array<string, array<string, Geometry[]>> // identifierType, identifierValue, Geometry
     */
    private array $geometries = [
        GeometryIdentifierType::MSP_ID => [],
        GeometryIdentifierType::OLD_ID => []
    ];

    /**
     * @var array<int, Grid> // gridOldId, Grid
     */
    private array $grids = [];

    public function getCountries(?callable $filter = null): array
    {
        return array_filter($this->countries, $filter);
    }

    public function getCountry(int $countryId): ?Country
    {
        return $this->countries[$countryId] ?? null;
    }

    public function addCountry(Country $country): void
    {
        $this->countries[$country->getCountryId()] = $country;
    }

    public function getLayer(string $layerName): ?Layer
    {
        if (null === $layer = $this->layers[strtolower($layerName)] ?? null) {
            return null;
        }
        $this->updateGeometriesByLayer($layer);
        return $layer;
    }

    public function filterOneLayer(callable $filter): ?Layer
    {
        if (null === $layer = current(array_filter($this->layers, $filter, ARRAY_FILTER_USE_BOTH)) ?: null) {
            return null;
        }
        $this->updateGeometriesByLayer($layer);
        return $layer;
    }

    public function addLayer(Layer $layer): void
    {
        $this->layers[strtolower($layer->getLayerName())] = $layer;
        $this->updateGeometriesByLayer($layer);
    }

    private function updateGeometriesByLayer(Layer $layer): void
    {
        foreach ($layer->getGeometry() as $geometry) {
            $this->addGeometry($geometry);
        }
    }

    /**
     * @return array<string, Geometry[]>
     */
    public function getGeometriesWithDuplicateMspId(): array
    {
        return array_filter(
            $this->geometries[GeometryIdentifierType::MSP_ID],
            fn($geometries) => count($geometries) > 1
        );
    }

    public function findOneGeometryByIdentifier(
        GeometryIdentifierType $identifierType,
        ?string $identifierValue
    ): ?Geometry {
        if ($identifierValue === null) {
            return null;
        }
        return $this->geometries[(string)$identifierType][$identifierValue][0] ?? null;
    }

    public function addGeometry(Geometry $geometry): void
    {
        $identifierValue = $geometry->getGeometryMspid();
        if ($identifierValue !== null) {
            $this->setGeometryByIdentifier(
                new GeometryIdentifierType(GeometryIdentifierType::MSP_ID),
                $identifierValue,
                $geometry
            );
        }
        $identifierValue = $geometry->getOldGeometryId();
        if ($identifierValue !== null) {
            $this->setGeometryByIdentifier(
                new GeometryIdentifierType(GeometryIdentifierType::OLD_ID),
                (string)$identifierValue,
                $geometry
            );
        }
    }

    private function setGeometryByIdentifier(
        GeometryIdentifierType $identifierType,
        string $identifierValue,
        Geometry $geometry
    ): void {
        $this->geometries[(string)$identifierType][$identifierValue] ??= [];
        $this->geometries[(string)$identifierType][$identifierValue][] = $geometry;
    }

    public function getGrid(int $gridOldId): ?Grid
    {
        return $this->grids[$gridOldId] ?? null;
    }

    public function addGrid(int $gridOldId, Grid $grid): void
    {
        $this->grids[$gridOldId] = $grid;
    }
}
