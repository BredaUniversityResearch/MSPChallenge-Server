<?php

namespace App\Entity\SessionAPI;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\OpenApi\Model\Operation;
use App\Domain\Common\EntityEnums\LayerGeoType;
use App\Domain\Services\ConnectionManager;
use App\Entity\EntityBase;
use App\Repository\SessionAPI\LayerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: LayerRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get()
    ],
    normalizationContext: ['groups' => ['read']],
    openapi: new Operation(
        tags: ['✨ Layer']
    )
)]
class Layer extends EntityBase
{
    const INTERPOLATION_TYPE_LIN = 'Lin';
    const INTERPOLATION_TYPE_LIN_GROUPED = 'LinGrouped';

    #[Groups(['read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $layerId = null;

    /**
     * @var Collection<int, Layer>
     */
    #[ORM\OneToMany(targetEntity: Layer::class, mappedBy: 'originalLayer', cascade: ['persist'])]
    private Collection $derivedLayer;

    #[ORM\ManyToOne(targetEntity: Layer::class, cascade: ['persist'], inversedBy: 'derivedLayer')]
    #[ORM\JoinColumn(name: 'layer_original_id', referencedColumnName: 'layer_id')]
    private ?Layer $originalLayer = null;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 1])]
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $layerActive = 1;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 1])]
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $layerSelectable = 1;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 0])]
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $layerActiveOnStart = 0;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 1])]
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $layerToggleable = 1;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 1])]
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $layerEditable = 1;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 125, options: ['default' => ''])]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $layerName = '';

    #[Groups(['read'])]
    #[ORM\Column(name: 'layer_geotype', nullable: true, enumType: LayerGeoType::class)]
    private ?LayerGeoType $layerGeoType = null;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 75, options: ['default' => ''])]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $layerShort = '';

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 75, options: ['default' => ''])]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $layerGroup = '';

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 512, options: ['default' => ''])]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $layerTooltip = '';

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 75, options: ['default' => 'management'])]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $layerCategory = 'management';

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 75, options: ['default' => 'aquaculture'])]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $layerSubcategory = 'aquaculture';

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 75, options: ['default' => 'Miscellaneous'])]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $layerKpiCategory = 'Miscellaneous';

    #[Groups(['read'])]
    #[ORM\Column(type: 'json_document', nullable: true)]
    private mixed $layerType = null;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::SMALLINT, length: 3, options: ['default' => 1])]
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $layerDepth = 1;

    #[Groups(['read'])]
    #[ORM\Column(type: 'json_document', nullable: true)]
    private mixed $layerInfoProperties = null;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 1024, nullable: true)]
    private ?string $layerInformation = null;

    #[Groups(['read'])]
    #[ORM\Column(
        type: 'json_document',
        nullable: false,
        options: ['default' => '{}']
    )]
    private ?LayerTextInfo $layerTextInfo = null;

    #[Groups(['read'])]
    #[ORM\Column(
        type: 'json_document',
        nullable: true,
        options: [
            'default' => '[{"state":"ASSEMBLY","time":2},{"state":"ACTIVE","time":10},{"state":"DISMANTLE","time":2}]'
        ]
    )]
    private ?array $layerStates = [
        ['state' => 'ASSEMBLY', 'time' => 2],
        ['state' => 'ACTIVE', 'time' => 10],
        ['state' => 'DISMANTLE', 'time' => 2],
    ];

    #[Groups(['read'])]
    #[ORM\Column(type: 'json_document', nullable: true)]
    private ?LayerRaster $layerRaster = null;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::FLOAT, options: ['default' => 100])]
    // @phpstan-ignore-next-line float|null but database expects float
    private ?float $layerLastupdate = 100;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::SMALLINT, length: 4, options: ['default' => 0])]
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $layerMelupdate = 0;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $layerEditingType = null;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 512, options: ['default' => 'Default'])]
    // @phpstan-ignore-next-line string|null but database expects string
    private ?string $layerSpecialEntityType = 'Default';

    #[Groups(['read'])]
    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 0])]
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $layerGreen = 0;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 0])]
    // @phpstan-ignore-next-line int|null but database expects int
    private ?int $layerMelupdateConstruction = 0;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0])]
    // @phpstan-ignore-next-line float|null but database expects float
    private ?float $layerFilecreationtime = 0;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $layerMedia = null;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $layerEntityValueMax = null;

    #[Groups(['read'])]
    #[ORM\Column(type: 'json_document', nullable: true)]
    private mixed $layerTags = null;

    /**
     * @var Collection<int, Geometry>
     */
    #[ORM\OneToMany(targetEntity: Geometry::class, mappedBy: 'layer', cascade: ['persist'], orphanRemoval: true)]
    private Collection $geometry;

    /**
     * @var Collection<int, Restriction>
     */
    #[ORM\OneToMany(targetEntity: Restriction::class, mappedBy: 'restrictionStartLayer', cascade: ['persist'])]
    private Collection $restrictionStart;

    /**
     * @var Collection<int, Restriction>
     */
    #[ORM\OneToMany(targetEntity: Restriction::class, mappedBy: 'restrictionEndLayer', cascade: ['persist'])]
    private Collection $restrictionEnd;

    /**
     * @var Collection<int, Layer>
     */
    #[ORM\ManyToMany(targetEntity: Layer::class, mappedBy: 'pressureGeneratingLayer', cascade: ['persist'])]
    private Collection $pressure;

    /**
     * @var Collection<int, Layer>
     */
    #[ORM\JoinTable(name: 'mel_layer')]
    #[ORM\JoinColumn(name: 'mel_layer_pressurelayer', referencedColumnName: 'layer_id')]
    #[ORM\InverseJoinColumn(name: 'mel_layer_layer_id', referencedColumnName: 'layer_id')]
    #[ORM\ManyToMany(targetEntity: Layer::class, inversedBy: 'pressure', cascade: ['persist'])]
    private Collection $pressureGeneratingLayer;

    /**
     * @var Collection<int, PlanLayer>
     */
    #[ORM\OneToMany(targetEntity: PlanLayer::class, mappedBy: 'layer', cascade: ['persist'], orphanRemoval: true)]
    private Collection $planLayer;

    /**
     * @var Collection<int, PlanDelete>
     */
    #[ORM\OneToMany(targetEntity: PlanDelete::class, mappedBy: 'layer', cascade: ['persist'])]
    private Collection $planDelete;

    /**
     * @var Collection<int, PlanRestrictionArea>
     */
    #[ORM\OneToMany(targetEntity: PlanRestrictionArea::class, mappedBy: 'layer', cascade: ['persist'])]
    private Collection $planRestrictionArea;

    private bool $layerGeometryWithGeneratedMspids = false;

    private ?bool $layerDownloadFromGeoserver = null;

    private ?string $layerPropertyAsType = null;

    private ?int $layerWidth = null;

    private ?int $layerHeight = null;

    private array $layerDependencies = [];

    private ?array $scale = null;

    private ?float $ecologyKpiValue = null;

    public function __construct()
    {
        $this->layerTextInfo = new LayerTextInfo();
        $this->derivedLayer = new ArrayCollection();
        $this->geometry = new ArrayCollection();
        $this->restrictionStart = new ArrayCollection();
        $this->restrictionEnd = new ArrayCollection();
        $this->pressure = new ArrayCollection();
        $this->pressureGeneratingLayer = new ArrayCollection();
        $this->planLayer = new ArrayCollection();
        $this->planDelete = new ArrayCollection();
        $this->planRestrictionArea = new ArrayCollection();
    }

    public function getDerivedLayer(): Collection
    {
        return $this->derivedLayer;
    }

    public function addDerivedLayer(Layer $derivedLayer): static
    {
        if (!$this->derivedLayer->contains($derivedLayer)) {
            $this->derivedLayer->add($derivedLayer);
            $derivedLayer->setOriginalLayer($this);
        }

        return $this;
    }

    public function removeDerivedLayer(Layer $derivedLayer): static
    {
        if ($this->derivedLayer->removeElement($derivedLayer)) {
            // set the owning side to null (unless already changed)
            if ($derivedLayer->getOriginalLayer() === $this) {
                $derivedLayer->setOriginalLayer(null);
            }
        }

        return $this;
    }

    #[Groups(['read'])]
    public function getOriginalLayerId(): ?int
    {
        return $this->originalLayer?->getLayerId();
    }

    public function getOriginalLayer(): ?Layer
    {
        return $this->originalLayer;
    }

    public function setOriginalLayer(?Layer $originalLayer): static
    {
        $this->originalLayer = $originalLayer;
        return $this;
    }

    public function hasGeometryWithGeneratedMspids(): bool
    {
        return $this->layerGeometryWithGeneratedMspids;
    }

    public function isGeometryWithGeneratedMspids(): static
    {
        $this->layerGeometryWithGeneratedMspids = true;
        return $this;
    }

    public function getLayerWidth(): ?int
    {
        return $this->layerWidth;
    }

    public function setLayerWidth(?int $layerWidth): static
    {
        $this->layerWidth = $layerWidth;
        return $this;
    }

    public function getLayerHeight(): ?int
    {
        return $this->layerHeight;
    }

    public function setLayerHeight(?int $layerHeight): static
    {
        $this->layerHeight = $layerHeight;
        return $this;
    }

    public function getLayerDownloadFromGeoserver(): ?bool
    {
        return $this->layerDownloadFromGeoserver;
    }

    public function setLayerDownloadFromGeoserver(?bool $layerDownloadFromGeoserver): static
    {
        $this->layerDownloadFromGeoserver = $layerDownloadFromGeoserver;
        return $this;
    }

    public function getLayerPropertyAsType(): ?string
    {
        return $this->layerPropertyAsType;
    }

    public function setLayerPropertyAsType(?string $layerPropertyAsType): static
    {
        $this->layerPropertyAsType = $layerPropertyAsType;
        return $this;
    }

    public function getLayerId(): ?int
    {
        return $this->layerId;
    }

    public function setLayerId(?int $layerId): static
    {
        $this->layerId = $layerId;
        return $this;
    }

    public function getLayerActive(): ?int
    {
        return $this->layerActive;
    }

    public function setLayerActive(?int $layerActive): static
    {
        $this->layerActive = $layerActive;
        return $this;
    }

    public function getLayerSelectable(): ?int
    {
        return $this->layerSelectable;
    }

    public function setLayerSelectable(?int $layerSelectable): static
    {
        $this->layerSelectable = $layerSelectable;
        return $this;
    }

    public function getLayerActiveOnStart(): ?int
    {
        return $this->layerActiveOnStart;
    }

    public function setLayerActiveOnStart(?int $layerActiveOnStart): static
    {
        $this->layerActiveOnStart = $layerActiveOnStart;
        return $this;
    }

    public function getLayerToggleable(): ?int
    {
        return $this->layerToggleable;
    }

    public function setLayerToggleable(?int $layerToggleable): static
    {
        $this->layerToggleable = $layerToggleable;
        return $this;
    }

    public function getLayerEditable(): ?int
    {
        return $this->layerEditable;
    }

    public function setLayerEditable(?int $layerEditable): static
    {
        $this->layerEditable = $layerEditable;
        return $this;
    }

    public function getLayerName(): ?string
    {
        return $this->layerName;
    }

    public function setLayerName(?string $layerName): static
    {
        $this->layerName = $layerName;
        return $this;
    }

    public function getLayerGeoType(): ?LayerGeoType
    {
        return $this->layerGeoType;
    }

    public function setLayerGeoType(?LayerGeoType $layerGeoType): static
    {
        $this->layerGeoType = $layerGeoType;
        return $this;
    }

    public function getLayerShort(): ?string
    {
        return $this->layerShort;
    }

    public function setLayerShort(?string $layerShort): static
    {
        $this->layerShort = $layerShort;
        return $this;
    }

    public function getLayerGroup(): ?string
    {
        return $this->layerGroup;
    }

    public function setLayerGroup(?string $layerGroup): static
    {
        $this->layerGroup = $layerGroup;
        return $this;
    }

    public function getLayerTooltip(): ?string
    {
        return $this->layerTooltip;
    }

    public function setLayerTooltip(?string $layerTooltip): static
    {
        if (is_null($layerTooltip)) {
            $layerTooltip = "";
        }
        $this->layerTooltip = $layerTooltip;
        return $this;
    }

    public function getLayerCategory(): ?string
    {
        return $this->layerCategory;
    }

    public function setLayerCategory(?string $layerCategory): static
    {
        $this->layerCategory = $layerCategory;
        return $this;
    }

    public function getLayerSubcategory(): ?string
    {
        return $this->layerSubcategory;
    }

    public function setLayerSubcategory(?string $layerSubcategory): static
    {
        $this->layerSubcategory = $layerSubcategory;
        return $this;
    }

    public function getLayerKpiCategory(): ?string
    {
        return $this->layerKpiCategory;
    }

    public function setLayerKpiCategory(?string $layerKpiCategory): static
    {
        $this->layerKpiCategory = $layerKpiCategory;
        return $this;
    }

    public function getLayerType(): mixed
    {
        return $this->layerType;
    }

    public function setLayerType(mixed $layerType): static
    {
        $this->layerType = $layerType;
        return $this;
    }

    public function getLayerDepth(): ?int
    {
        return $this->layerDepth;
    }

    public function setLayerDepth(?int $layerDepth): static
    {
        $this->layerDepth = $layerDepth;
        return $this;
    }

    public function getLayerInfoProperties(): mixed
    {
        return $this->layerInfoProperties;
    }

    public function setLayerInfoProperties(mixed $layerInfoProperties): static
    {
        $this->layerInfoProperties = $layerInfoProperties;
        return $this;
    }

    public function getLayerInformation(): ?string
    {
        return $this->layerInformation;
    }

    public function setLayerInformation(?string $layerInformation): static
    {
        $this->layerInformation = $layerInformation;
        return $this;
    }

    public function getLayerTextInfo(): LayerTextInfo
    {
        $this->layerTextInfo ??= new LayerTextInfo();
        return $this->layerTextInfo;
    }

    public function setLayerTextInfo(LayerTextInfo|array $layerTextInfo): static
    {
        if (is_array($layerTextInfo)) {
            $layerTextInfo = new LayerTextInfo($layerTextInfo);
        }
        $this->layerTextInfo = $layerTextInfo;
        return $this;
    }

    public function getLayerStates(): mixed
    {
        return $this->layerStates;
    }

    public function setLayerStates(mixed $layerStates): static
    {
        $this->layerStates = $layerStates;
        return $this;
    }

    public function getLayerRaster(): ?LayerRaster
    {
        return $this->layerRaster;
    }

    public function getLayerRasterAsJson(): ?string
    {
        return json_encode($this->layerRaster);
    }

    public function setLayerRaster(?LayerRaster $layerRaster = null): static
    {
        $this->layerRaster = $layerRaster;
        return $this;
    }

    public function getLayerLastupdate(): ?float
    {
        return $this->layerLastupdate;
    }

    public function setLayerLastupdate(?float $layerLastupdate): static
    {
        $this->layerLastupdate = $layerLastupdate;
        return $this;
    }

    public function getLayerMelupdate(): ?int
    {
        return $this->layerMelupdate;
    }

    public function setLayerMelupdate(?int $layerMelupdate): static
    {
        $this->layerMelupdate = $layerMelupdate;
        return $this;
    }

    public function getLayerEditingType(): ?string
    {
        return $this->layerEditingType;
    }

    public function setLayerEditingType(?string $layerEditingType): static
    {
        $this->layerEditingType = $layerEditingType;
        return $this;
    }

    public function getLayerSpecialEntityType(): ?string
    {
        return $this->layerSpecialEntityType;
    }

    public function setLayerSpecialEntityType(?string $layerSpecialEntityType): static
    {
        $this->layerSpecialEntityType = $layerSpecialEntityType;
        return $this;
    }

    public function getLayerGreen(): ?int
    {
        return $this->layerGreen;
    }

    public function setLayerGreen(?int $layerGreen): static
    {
        $this->layerGreen = $layerGreen;
        return $this;
    }

    public function getLayerMelupdateConstruction(): ?int
    {
        return $this->layerMelupdateConstruction;
    }

    public function setLayerMelupdateConstruction(?int $layerMelupdateConstruction): static
    {
        $this->layerMelupdateConstruction = $layerMelupdateConstruction;
        return $this;
    }

    public function getLayerFilecreationtime(): ?float
    {
        return $this->layerFilecreationtime;
    }

    public function setLayerFilecreationtime(?float $layerFilecreationtime): static
    {
        $this->layerFilecreationtime = $layerFilecreationtime;
        return $this;
    }

    public function getLayerMedia(): ?string
    {
        return $this->layerMedia;
    }

    public function setLayerMedia(?string $layerMedia): static
    {
        $this->layerMedia = $layerMedia;
        return $this;
    }

    public function getLayerEntityValueMax(): ?float
    {
        return $this->layerEntityValueMax;
    }

    public function setLayerEntityValueMax(?float $layerEntityValueMax): static
    {
        $this->layerEntityValueMax = $layerEntityValueMax;
        return $this;
    }

    public function getLayerTags(): mixed
    {
        return $this->layerTags;
    }

    public function setLayerTags(mixed $layerTags): static
    {
        $this->layerTags = $layerTags;
        return $this;
    }

    /**
     * @return Collection<int, Geometry>
     */
    public function getGeometry(): Collection
    {
        return $this->geometry;
    }

    public function addGeometry(Geometry $geometry): self
    {
        if (!$this->geometry->contains($geometry)) {
            $this->geometry->add($geometry);
            $geometry->setLayer($this);
        }

        return $this;
    }

    public function removeGeometry(Geometry $geometry): self
    {
        $this->geometry->removeElement($geometry);
        // Since orphanRemoval is set, no need to explicitly remove $geometry from the database
        return $this;
    }

    public function getRestrictionStart(): Collection
    {
        return $this->restrictionStart;
    }

    public function addRestrictionStart(Restriction $restriction): self
    {
        if (!$this->restrictionStart->contains($restriction)) {
            $this->restrictionStart->add($restriction);
            $restriction->setRestrictionStartLayer($this);
        }

        return $this;
    }

    public function removeRestrictionStart(Restriction $restriction): self
    {
        if ($this->restrictionStart->removeElement($restriction)) {
            // set the owning side to null (unless already changed)
            if ($restriction->getRestrictionStartLayer() === $this) {
                $restriction->setRestrictionStartLayer(null);
            }
        }

        return $this;
    }

    public function getRestrictionEnd(): Collection
    {
        return $this->restrictionEnd;
    }

    public function addRestrictionEnd(Restriction $restriction): self
    {
        if (!$this->restrictionEnd->contains($restriction)) {
            $this->restrictionEnd->add($restriction);
            $restriction->setRestrictionEndLayer($this);
        }

        return $this;
    }

    public function removeRestrictionEnd(Restriction $restriction): self
    {
        if ($this->restrictionEnd->removeElement($restriction)) {
            // set the owning side to null (unless already changed)
            if ($restriction->getRestrictionEndLayer() === $this) {
                $restriction->setRestrictionEndLayer(null);
            }
        }

        return $this;
    }

    public function getPressure(): Collection
    {
        return $this->pressure;
    }

    public function addPressure(Layer $layer): self
    {
        if (!$this->pressure->contains($layer)) {
            $this->pressure->add($layer);
            $layer->addPressureGeneratingLayer($this);
        }

        return $this;
    }

    public function removePressure(Layer $layer): self
    {
        if ($this->pressure->removeElement($layer)) {
            $layer->removePressureGeneratingLayer($this);
        }

        return $this;
    }

    public function getPressureGeneratingLayer(): Collection
    {
        return $this->pressureGeneratingLayer;
    }

    public function addPressureGeneratingLayer(Layer $layer): self
    {
        if (!$this->pressureGeneratingLayer->contains($layer)) {
            $this->pressureGeneratingLayer->add($layer);
            $layer->addPressure($this);
        }

        return $this;
    }

    public function removePressureGeneratingLayer(Layer $layer): self
    {
        if ($this->pressureGeneratingLayer->removeElement($layer)) {
            $layer->removePressure($this);
        }
        return $this;
    }

    /**
     * @return Collection<int, PlanLayer>
     */
    public function getPlanLayer(): Collection
    {
        return $this->planLayer;
    }

    public function addPlanLayer(PlanLayer $planLayer): self
    {
        if (!$this->planLayer->contains($planLayer)) {
            $this->planLayer->add($planLayer);
            $planLayer->setLayer($this);
        }

        return $this;
    }

    public function removePlanLayer(PlanLayer $planLayer): self
    {
        $this->planLayer->removeElement($planLayer);
        // Since orphanRemoval is set, no need to explicitly remove $planLayer from the database
        return $this;
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
            $planDelete->setLayer($this);
        }

        return $this;
    }

    public function removePlanDelete(PlanDelete $planDelete): self
    {
        if ($this->planDelete->removeElement($planDelete)) {
            // set the owning side to null (unless already changed)
            if ($planDelete->getLayer() === $this) {
                $planDelete->setLayer(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PlanRestrictionArea>
     */
    public function getPlanRestrictionArea(): Collection
    {
        return $this->planRestrictionArea;
    }

    public function addPlanRestrictionArea(PlanRestrictionArea $planRestrictionArea): self
    {
        if (!$this->planRestrictionArea->contains($planRestrictionArea)) {
            $this->planRestrictionArea->add($planRestrictionArea);
            $planRestrictionArea->setLayer($this);
        }

        return $this;
    }

    public function removePlanRestrictionArea(PlanRestrictionArea $planRestrictionArea): self
    {
        if ($this->planRestrictionArea->removeElement($planRestrictionArea)) {
            // set the owning side to null (unless already changed)
            if ($planRestrictionArea->getLayer() === $this) {
                $planRestrictionArea->setLayer(null);
            }
        }

        return $this;
    }

    public function exportToDecodedGeoJSON(): array
    {
        $arrayToEncode = [];
        foreach ($this->getGeometry() as $geometry) {
            $arrayToEncode[] = $geometry->exportToDecodedGeoJSON();
        }
        foreach ($this->getDerivedLayer() as $derivedLayer) {
            foreach ($derivedLayer->getGeometry() as $derivedLayerGeometry) {
                $arrayToEncode[] = $derivedLayerGeometry->exportToDecodedGeoJSON();
            }
        }
        return $arrayToEncode;
    }

    public function getLayerDependencies(): array
    {
        return $this->layerDependencies;
    }

    public function setLayerDependencies(array $layerDependencies): void
    {
        $this->layerDependencies = $layerDependencies;
    }

    /**
     * Gets scale for the raster layers
     * @throws Exception
     */
    #[Groups(['read'])]
    public function getScale(): ?array
    {
        if (null !== $ecologyKpiValue = $this->getEcologyKpiValue()) {
            $this->scale ??= [
                'min_value' => 0,
                # convert from t/km2  to kg/km2, times 2 since 0.5 is the reference value
                'max_value' => $ecologyKpiValue * 1000 * 2,
                'interpolation' => self::INTERPOLATION_TYPE_LIN
            ];
        }

        // only continue for raster layers of type ValueMap
        if (!in_array('ValueMap', $this->getLayerTags() ?? [])) {
            return $this->scale;
        }

        /*
         * - the layer has a heatmap range data in the game config data model.
         *   The scale will be of interpolation type LinGrouped
         */
        $this->scale ??= $this->getScaleFromSELHeatMapRange($this->getSELHeatmapRange());

        // the layer type names can be parsed to extract min and max values. The scale will be of interpolation type Lin
        $this->scale ??= $this->getScaleFromTypeMapping();
        return $this->scale;
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function getEcologyKpiValue(): ?float
    {
        if (null !== $this->ecologyKpiValue) {
            return $this->ecologyKpiValue;
        }
        if (null == $conn = ConnectionManager::getInstance()->getCachedGameSessionDbConnection(
            $this->getOriginGameListId()
        )) {
            return null;
        }
        $result = $conn->executeQuery(
            <<<'SQL'
WITH
  # group ecology kpis by name and give row number based on month, row number 1 is the latest kpi
  LatestEcologyKpiStep1 AS (
    SELECT
      *,
      ROW_NUMBER() OVER (PARTITION BY kpi_name ORDER BY kpi_month DESC) AS rn
    FROM
      kpi
    WHERE kpi_type = 'ECOLOGY'
  ),
  # filter latest ecology kpis, so only with row number 1
  LatestEcologyKpiFinal AS (
    SELECT * FROM LatestEcologyKpiStep1 WHERE rn = 1
  ),
  # include kpi value if layer is an ecology layer
  LayerEcologyKpiValue AS (
      SELECT
        l.name_unique_when_original_id_null as layer_name,
        k.kpi_value
      FROM layer l
      LEFT JOIN LatestEcologyKpiFinal k ON (
          CONCAT('mel_',LOWER(REPLACE(k.kpi_name,' ','_'))) = l.name_unique_when_original_id_null
      )
      WHERE l.name_unique_when_original_id_null IS NOT NULL
  )
SELECT kpi_value FROM LayerEcologyKpiValue l WHERE l.layer_name = :layer_name
SQL,
            ['layer_name' => $this->getLayerName()]
        );
        $this->ecologyKpiValue = $result->fetchOne() ?: null;
        return $this->ecologyKpiValue;
    }

    /**
     * The game config data model (from the config file) has a ["SEL"]["heatmap_settings"]["heatmap_range"],
     *   that is available for each SEL layer. E.g., for shipping intensity layers.
     * This function will try to retrieve that heatmap_range array for the specified layer
     *   or null if it is not available, e.g., if it is not a SEL layer
     * @throws Exception
     */
    private function getSELHeatmapRange(): ?array
    {
        if (null === $this->getLayerName()) {
            return null;
        }
        $game = new \App\Domain\API\v1\Game();
        $gameConfigDataModel = $game->GetGameConfigValues($this->getOriginSessionLogFilePath() ?? '');
        $heatmapSettings = array_filter(
            $gameConfigDataModel['SEL']['heatmap_settings'] ?? [],
            fn($x) => $x['layer_name'] === $this->getLayerName()
        );
        if (empty($heatmapSettings)) {
            return null;
        }
        $heatMapSetting = current($heatmapSettings);
        if (!array_key_exists('heatmap_range', $heatMapSetting)) {
            return null;
        }
        if (empty($heatMapSetting['heatmap_range'])) {
            return null;
        }
        return $heatMapSetting['heatmap_range'];
    }

    /**
     * Given layer mapping and type names data from the game config data model, it will try to create a scale
     *
     * e.g. for "NO Hywind Metcentre"'s Bathymetry layer has mapping:
     *  [
     *      {"max": 0,"type": 0,"min": 0},
     *      {"max": 37,"type": 1,"min": 1},
     *      {"max": 73,"type": 2,"min": 38},
     *      {"max": 110,"type": 3,"min": 74},
     *      {"max": 146,"type": 4,"min": 111},
     *      {"max": 183,"type": 5,"min": 147},
     *      {"max": 219,"type": 6,"min": 184},
     *      {"max": 255,"type": 7,"min": 220}
     *  ]
     * and type names:
     *   ["0 - 20 m","20 - 40 m","40 - 60 m","60 -100 m","100 - 200 m","200 - 500 m ","500 - 1000 m ","> 1000 m"]
     *
     * It also supports the special case "<" for the first type name, e.g.,
     *   for "NO Hywind Metcentre"'s Wind Speed layer:
     *   ["< 5.0 m\\/s","5.0 - 6.0 m\\/s","6.0 - 7.0 m\\/s","7.0 - 8.0 m\\/s","8.0 - 9.0 m\\/s","> 9.0 m\\/s"]
     * @throws Exception
     */
    private function getScaleFromTypeMapping(): ?array
    {
        $layerMapping = collect($this->getLayerType())
            ->map(fn(array $lt, $key) => ['max' => $lt['value'], 'type' => $key])->all();
        self::normaliseAndExtendRasterMapping($layerMapping);
        $layerTypeNames = collect($this->getLayerType())
            ->map(fn(array $lt) => $lt['displayName'] ?? $lt['name'])->all();

        // using heatmap format as intermediate format
        $heatmapRange= [];
        foreach ($layerMapping as $mapping) {
            $typeIndex = $mapping['type'];
            if (!array_key_exists($typeIndex, $layerTypeNames)) {
                return null;
            }
            $layerTypeName = $layerTypeNames[$typeIndex];
            if (false === preg_match_all('/(\d+(?:\.\d+)?)|<\s/', $layerTypeName, $matches)) {
                return null;
            }
            if (!isset($matches[0][0])) {
                return null;
            }
            if ($matches[0][0] === '<') {
                $matches[0][0] = 0;
            }
            $heatmapRangeEl['input'] = (float)$matches[0][0];
            $heatmapRangeEl['output'] = $mapping['min'] / 255;
            $heatmapRange[] = $heatmapRangeEl;
        }

        $num = count($heatmapRange);
        if ($num < 3) {
            return $this->getScaleFromSELHeatMapRange($heatmapRange);
        }

        // add additional element to fill-up to 255, use the same range as the one of the last 2 elements
        $lastRange = $heatmapRange[$num - 1]['input'] - $heatmapRange[$num - 2]['input'];
        $heatmapRangeEl['input'] = $heatmapRange[$num - 1]['input'] + $lastRange;
        $heatmapRangeEl['output'] = 1;
        $heatmapRange[] = $heatmapRangeEl;

        return $this->getScaleFromSELHeatMapRange($heatmapRange);
    }

    /**
     * Given the layer mapping, normalises the max values, up to 255 max, and:
     *   sets the "min" value based on the previous mapping entry's max
     */
    public static function normaliseAndExtendRasterMapping(array &$mapping): void
    {
        $m = &$mapping;
        if (count($m) == 0) {
            return;
        }
        $maxValue = $m[count($m)-1]['max'];
        $m[0]['min'] = 0;
        if ($maxValue < PHP_FLOAT_EPSILON) {
            return;
        }
        $m[0]['max'] = (int)ceil(($m[0]['max'] / $maxValue) * 255);
        for ($n = 1; $n < count($m); ++$n) {
            $prevMappingEntry = &$m[$n - 1];
            $mappingEntry = &$m[$n];
            $mappingEntry['min'] = $prevMappingEntry['max']+1;
            $m[$n]['max'] = (int)ceil(($m[$n]['max'] / $maxValue) * 255);
        }
    }

    /**
     * Given a layer's SEL heatmap range from the game config file, it will try to create a scale from it
     * The scale can be of interpolation type Lin or LinGrouped
     *
     * @throws Exception
     */
    private function getScaleFromSELHeatMapRange(?array $heatmapRange): ?array
    {
        if (null === $heatmapRange) {
            return null;
        }
        if (empty($heatmapRange) || count($heatmapRange) < 2) {
            return null;
        }
        $scale = [
            'min_value' => current($heatmapRange)['input'],
            'max_value' => end($heatmapRange)['input'],
            'interpolation' => self::INTERPOLATION_TYPE_LIN
        ];

        if (count($heatmapRange) < 3) {
            return $scale;
        }

        $scale['interpolation'] = self::INTERPOLATION_TYPE_LIN_GROUPED;
        $scale['groups'] = array_map(fn($x) => [
            'normalised_input_value' => $x['output'],
            'min_output_value' => $x['input']
        ], $heatmapRange);

        return $scale;
    }
}
