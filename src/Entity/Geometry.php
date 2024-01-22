<?php

namespace App\Entity;

use App\Repository\GeometryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\JoinColumn;

#[ORM\Entity(repositoryClass: GeometryRepository::class)]

class Geometry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $geometryId;

    #[ORM\Column(type: Types::INTEGER, length: 11, nullable: true)]
    private ?int $geometryPersistent;

    #[ORM\Column(type: Types::STRING, length: 75, nullable: true)]
    private ?string $geometryFID;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $geometryGeometry;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $geometryData;

    #[ORM\Column(type: Types::INTEGER, length: 11, nullable: true)]
    private ?int $geometryCountryId;

    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 1])]
    private ?int $geometryActive = 1;

    #[ORM\Column(type: Types::INTEGER, length: 11, options: ['default' => 0])]
    private ?int $geometrySubtractive = 0;

    #[ORM\Column(type: Types::STRING, length: 75, options: ['default' => '0'])]
    private ?string $geometryType = '0';

    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 0])]
    private ?int $geometryDeleted = 0;

    #[ORM\Column(type: Types::STRING, length: 16, nullable: true)]
    private ?string $geometryMspid;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'geometry')]
    #[JoinColumn(name: 'geometry_layer_id', referencedColumnName: 'layer_id')]
    private ?Layer $layer;

    public function getGeometryId(): ?int
    {
        return $this->geometryId;
    }

    public function setGeometryId(?int $geometryId): Geometry
    {
        $this->geometryId = $geometryId;
        return $this;
    }

    public function getLayer(): Layer
    {
        return $this->layer;
    }

    public function setLayer(?Layer $layer): Geometry
    {
        $this->layer = $layer;
        return $this;
    }

    public function getGeometryPersistent(): ?int
    {
        return $this->geometryPersistent;
    }

    public function setGeometryPersistent(?int $geometryPersistent): Geometry
    {
        $this->geometryPersistent = $geometryPersistent;
        return $this;
    }

    public function getGeometryFID(): ?string
    {
        return $this->geometryFID;
    }

    public function setGeometryFID(?string $geometryFID): Geometry
    {
        $this->geometryFID = $geometryFID;
        return $this;
    }

    public function getGeometryGeometry(): ?string
    {
        return $this->geometryGeometry;
    }

    public function setGeometryGeometry(?string $geometryGeometry): Geometry
    {
        $this->geometryGeometry = $geometryGeometry;
        return $this;
    }

    public function getGeometryData(): ?string
    {
        return $this->geometryData;
    }

    public function setGeometryData(?string $geometryData): Geometry
    {
        $this->geometryData = $geometryData;
        return $this;
    }

    public function getGeometryCountryId(): ?int
    {
        return $this->geometryCountryId;
    }

    public function setGeometryCountryId(?int $geometryCountryId): Geometry
    {
        $this->geometryCountryId = $geometryCountryId;
        return $this;
    }

    public function getGeometryActive(): ?int
    {
        return $this->geometryActive;
    }

    public function setGeometryActive(?int $geometryActive): Geometry
    {
        $this->geometryActive = $geometryActive;
        return $this;
    }

    public function getGeometrySubtractive(): ?int
    {
        return $this->geometrySubtractive;
    }

    public function setGeometrySubtractive(?int $geometrySubtractive): Geometry
    {
        $this->geometrySubtractive = $geometrySubtractive;
        return $this;
    }

    public function getGeometryType(): ?string
    {
        return $this->geometryType;
    }

    public function setGeometryType(?string $geometryType): Geometry
    {
        if (!empty($layerMetaData["layer_property_as_type"])) {
            // check if the layer_property_as_type value exists in $featureProperties
            $type = '-1';
            if (!empty($featureProperties[$layerMetaData["layer_property_as_type"]])) {
                $featureTypeProperty = $featureProperties[$layerMetaData["layer_property_as_type"]];
                foreach ($layerMetaData["layer_type"] as $layerTypeMetaData) {
                    if (!empty($layerTypeMetaData["map_type"])) {
                        // identify the 'other' category
                        if (strtolower($layerTypeMetaData["map_type"]) == "other") {
                            $typeOther = $layerTypeMetaData["value"];
                        }
                        // translate the found $featureProperties value to the type value
                        if ($layerTypeMetaData["map_type"] == $featureTypeProperty) {
                            $type = $layerTypeMetaData["value"];
                            break;
                        }
                    }
                }
            }
            if ($type == -1) {
                $type = $typeOther ?? 0;
            }
        } else {
            $type = (int)($featureProperties['type'] ?? 0);
            unset($featureProperties['type']);
        }
        $this->geometryType = $geometryType;
        return $this;
    }

    public function getGeometryDeleted(): ?int
    {
        return $this->geometryDeleted;
    }

    public function setGeometryDeleted(?int $geometryDeleted): Geometry
    {
        $this->geometryDeleted = $geometryDeleted;
        return $this;
    }

    public function getGeometryMspid(): ?string
    {
        return $this->geometryMspid;
    }

    public function setGeometryMspid(?string $geometryMspid): Geometry
    {
        if ($this->getGeometrySubtractive() === 0 && empty($geometryMspid)) {
            $algo = 'fnv1a64';
            $dataToHash = $this->getLayer()->getLayerName() . $this->getGeometryGeometry();
            $dataArray = json_decode($this->getGeometryData(), true);
            $dataToHash .= $dataArray['name'] ?? '';
            $this->geometryMspid = hash($algo, $dataToHash);
        }
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function processLayerGeometry(array $feature): bool
    {
        // getting geometryType, geometryMspId, geometryCountryId in order, and cleaning up $feature["properties"]
        // setting geometryData through $feature["properties"]
        // setting geometryGeometry through $feature["geometry"]
        // ensuring Multidata (Multipoint, -linestring, -polygon), making sure we handle subtractive polygons ('holes')
        $this->setGeometryType($feature['properties']);

        if (empty($feature["geometry"])) {
            throw new \Exception("Could not import geometry with id {$feature['id']} of layer
                {$layer->getLayerName()}. The feature in question has NULL geometry.
                Some property information to help you find the feature:".
                substr(
                    var_export($feature["properties"], true),
                    0,
                    80
                )
            );
        }
        $feature = $this->moveDataFromArray($layerMetaData, $feature);
        $this->setGeometryLayerId($layer->getLayerId());
        if (!$this->processAndAddGeometry($feature, $layer->getLayerId(), $layerMetaData)) {
            $this->gameSessionLogger->error(
                'Could not import geometry with id {featureId} of layer {layerName} into database.'.
                ' Some property information to help you find the feature: ',
                [
                    'featureId' => $feature['id'],
                    'layerName' => $layerWithin['layerName'],
                    'propertyInfo' => substr(
                        var_export($feature['properties'], true),
                        0,
                        80
                    ),
                    'gameSession' => $this->gameSession->getId()
                ]
            );
        }
    }

    private function moveDataFromArray(
        array $layerMetaData,
        array $featureProperties
    ): array {
        if (!empty($layerMetaData["layer_property_as_type"])) {
            // check if the layer_property_as_type value exists in $featureProperties
            $type = '-1';
            if (!empty($featureProperties[$layerMetaData["layer_property_as_type"]])) {
                $featureTypeProperty = $featureProperties[$layerMetaData["layer_property_as_type"]];
                foreach ($layerMetaData["layer_type"] as $layerTypeMetaData) {
                    if (!empty($layerTypeMetaData["map_type"])) {
                        // identify the 'other' category
                        if (strtolower($layerTypeMetaData["map_type"]) == "other") {
                            $typeOther = $layerTypeMetaData["value"];
                        }
                        // translate the found $featureProperties value to the type value
                        if ($layerTypeMetaData["map_type"] == $featureTypeProperty) {
                            $type = $layerTypeMetaData["value"];
                            break;
                        }
                    }
                }
            }
            if ($type == -1) {
                $type = $typeOther ?? 0;
            }
        } else {
            $type = (int)($featureProperties['type'] ?? 0);
            unset($featureProperties['type']);
        }

        if (isset($featureProperties['mspid'])
            && is_numeric($featureProperties['mspid'])
            && intval($featureProperties['mspid']) !== 0
        ) {
            $mspId = intval($featureProperties['mspid']);
            unset($featureProperties['mspid']);
        }

        if (isset($featureProperties['country_id'])
            && is_numeric($featureProperties['country_id'])
            && intval($featureProperties['country_id']) !== 0
        ) {
            $countryId = intval($featureProperties['country_id']);
            unset($featureProperties['country_id']);
        }

        $feature['properties'] = $featureProperties;
        $feature['properties_msp']['type'] = $type;
        $feature['properties_msp']['mspId'] = $mspId ?? null;
        $feature['properties_msp']['countryId'] = $countryId ?? null;
        return $feature;
    }
}
