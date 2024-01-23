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

    /**
     * @param Layer|null $layer
     */
    public function __construct(?Layer $layer = null)
    {
        $this->layer = $layer;
    }


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

    public function setGeometryGeometry(array|string|null $geometryGeometry): Geometry
    {
        if (is_array($geometryGeometry)) {
            $geometryGeometry = json_encode($geometryGeometry);
        }
        $this->geometryGeometry = $geometryGeometry;
        return $this;
    }

    public function getGeometryData(): ?string
    {
        return $this->geometryData;
    }

    public function setGeometryData(array|string|null $geometryData): Geometry
    {
        if (is_array($geometryData)) {
            // assume $geometryData is actually a downloaded feature properties array
            unset($geometryData['type']);
            unset($geometryData['mspid']);
            unset($geometryData['country_id']);
            $geometryData = json_encode($geometryData);
        }
        $this->geometryData = $geometryData;
        return $this;
    }

    public function getGeometryCountryId(): ?int
    {
        return $this->geometryCountryId;
    }

    public function setGeometryCountryId(string|int|null $geometryCountryId): Geometry
    {
        if (is_string($geometryCountryId)) {
            $geometryCountryId = intval($geometryCountryId);
        }
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

    public function setGeometryType(string|array|null $geometryType): Geometry
    {
        if (is_array($geometryType)) {
            // assume $geometryType is actually a downloaded feature properties array
            $featureProperties = $geometryType;
            if (!empty($this->getLayer()->getLayerPropertyAsType())) {
                $type = '-1';
                if (!empty($featureProperties[$this->getLayer()->getLayerPropertyAsType()])) {
                    $featureTypeProperty = $featureProperties[$this->getLayer()->getLayerPropertyAsType()];
                    foreach ($this->getLayer()->getLayerType() as $typeValue => $layerTypeMetaData) {
                        if (!empty($layerTypeMetaData["map_type"])) {
                            // identify the 'other' category
                            if (strtolower($layerTypeMetaData["map_type"]) == "other") {
                                $typeOther = $typeValue;
                            }
                            if (str_contains($layerTypeMetaData["map_type"], '-')) {
                                // assumes a range of minimum to maximum (but not including) integer or float values
                                $typeValues = explode('-', $layerTypeMetaData["map_type"], 2);
                                if ((float) $featureTypeProperty >= (float) $typeValues[0]
                                    && (float) $featureTypeProperty < (float) $typeValues[1]) {
                                    $type = $typeValue;
                                }
                            } elseif ($layerTypeMetaData["map_type"] == $featureTypeProperty) {
                                // translate the found $featureProperties value to the type value (int, float, string)
                                $type = $typeValue;
                                break;
                            }
                        }
                    }
                }
                if ($type == '-1') {
                    $type = $typeOther ?? 0;
                }
                $this->geometryType = $type;
                return $this;
            }
            $this->geometryType = $featureProperties['type'] ?? '0';
            return $this;
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
            $this->getLayer()->setGeometryWithGeneratedMspids(true);
        }
        return $this;
    }

    public function setGeometryPropertiesThroughFeature(array $feature): self
    {
        $this->setGeometryData($feature);
        $this->setGeometryType($feature);
        $this->setGeometryCountryId($feature['country_id'] ?? null);
        $this->setGeometryMspid($feature['mspid'] ?? null);
        return $this;
    }
}
