<?php

namespace App\Entity;

use App\Repository\GeometryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

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

    #[ORM\OneToMany(mappedBy: 'geometryToSubtractFrom', targetEntity: Geometry::class, cascade: ['persist'])]
    private Collection $geometrySubtractives;

    /** Many Categories have One Category. */
    #[ORM\ManyToOne(targetEntity: Geometry::class, cascade: ['persist'], inversedBy: 'geometrySubtractive')]
    #[ORM\JoinColumn(name: 'geometry_subtractive', referencedColumnName: 'geometry_id')]
    private ?Geometry $geometryToSubtractFrom = null;

    #[ORM\Column(type: Types::STRING, length: 75, options: ['default' => '0'])]
    private ?string $geometryType = '0';

    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 0])]
    private ?int $geometryDeleted = 0;

    #[ORM\Column(type: Types::STRING, length: 16, nullable: true)]
    private ?string $geometryMspid;

    #[ORM\ManyToOne(cascade: ['persist'], inversedBy: 'geometry')]
    #[ORM\JoinColumn(name: 'geometry_layer_id', referencedColumnName: 'layer_id')]
    private ?Layer $layer;

    #[ORM\OneToMany(mappedBy: 'geometry', targetEntity: PlanDelete::class, cascade: ['persist'])]
    private Collection $planDelete;

    /**
     * @param Layer|null $layer
     */
    public function __construct(?Layer $layer = null)
    {
        $this->layer = $layer;
        $this->geometrySubtractives = new ArrayCollection();
        $this->planDelete = new ArrayCollection();
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

    /**
     * @return Collection<int, Geometry>
     */
    public function getGeometrySubtractives(): Collection
    {
        return $this->geometrySubtractives;
    }

    public function addGeometrySubtractive(Geometry $geometry): self
    {
        if (!$this->geometrySubtractives->contains($geometry)) {
            $this->geometrySubtractives->add($geometry);
            $geometry->setGeometryToSubtractFrom($this);
        }

        return $this;
    }

    public function removeGeometrySubtractive(Geometry $geometry): self
    {
        $this->geometrySubtractives->removeElement($geometry);
        $geometry->setGeometryToSubtractFrom(null);
        return $this;
    }

    public function getGeometryToSubtractFrom(): ?Geometry
    {
        return $this->geometryToSubtractFrom;
    }

    public function setGeometryToSubtractFrom(?Geometry $geometryToSubtractFrom): Geometry
    {
        $this->geometryToSubtractFrom = $geometryToSubtractFrom;
        if (!is_null($geometryToSubtractFrom)) {
            $geometryToSubtractFrom->addGeometrySubtractive($this);
            $this->setGeometryMspid(null);
        }
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
        if (!empty($this->getGeometryToSubtractFrom())) {
            $this->geometryMspid = null;
            return $this;
        }
        if (empty($geometryMspid)) {
            $algo = 'fnv1a64';
            $dataToHash = $this->getLayer()->getLayerName() . $this->getGeometryGeometry();
            $dataArray = json_decode($this->getGeometryData(), true);
            $dataToHash .= $dataArray['name'] ?? '';
            $this->geometryMspid = hash($algo, $dataToHash);
            $this->getLayer()->setGeometryWithGeneratedMspids(true);
            return $this;
        }
        $this->geometryMspid = $geometryMspid;
        return $this;
    }

    public function setGeometryPropertiesThroughFeature(array $featureProperties): self
    {
        $this->setGeometryData($featureProperties);
        $this->setGeometryType($featureProperties);
        $this->setGeometryCountryId($featureProperties['country_id'] ?? null);
        $this->setGeometryMspid($featureProperties['mspid'] ?? null);
        return $this;
    }

    public function calculateBounds(): array
    {
        if (empty($this->geometryGeometry)) {
            throw new \Exception('No geometry coordinates retrieved, cannot calculate bounds.');
        }
        $result = ["x_min" => 1e25, "y_min" => 1e25, "x_max" => -1e25, "y_max" => -1e25];
        foreach (json_decode($this->geometryGeometry, true) as $g) {
            if ($g[0] < $result["x_min"]) {
                $result["x_min"] = $g[0];
            }
            if ($g[1] < $result["y_min"]) {
                $result["y_min"] = $g[1];
            }
            if ($g[0] > $result["x_max"]) {
                $result["x_max"] = $g[0];
            }
            if ($g[1] > $result["y_max"]) {
                $result["y_max"] = $g[1];
            }
        }
        return $result;
    }

    /**
     * @return Collection<int, PlanDelete>
     */
    public function getPlanDelete(): Collection
    {
        return $this->planDelete;
    }

    public function addPlanDelete(PlanDelete $planDelete): self
    {
        if (!$this->planDelete->contains($planDelete)) {
            $this->planDelete->add($planDelete);
            $planDelete->setGeometry($this);
        }

        return $this;
    }

    public function removePlanDelete(PlanDelete $planDelete): self
    {
        if ($this->planDelete->removeElement($planDelete)) {
            // set the owning side to null (unless already changed)
            if ($planDelete->getGeometry() === $this) {
                $planDelete->setGeometry(null);
            }
        }

        return $this;
    }
}
