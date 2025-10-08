<?php

namespace App\Entity\SessionAPI;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model\Operation;
use App\Domain\Common\EntityEnums\LayerGeoType;
use App\Repository\SessionAPI\LayerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: LayerRepository::class)]
#[ApiResource(
    operations: [
        new Get()
    ],
    normalizationContext: ['groups' => ['read']],
    openapi: new Operation(
        tags: ['✨ Layer']
    )
)]
class Layer
{
    #[Groups(['read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $layerId = null;

    #[ORM\OneToMany(mappedBy: 'originalLayer', targetEntity: Layer::class, cascade: ['persist'])]
    private Collection $derivedLayer;

    #[ORM\ManyToOne(targetEntity: Layer::class, cascade: ['persist'], inversedBy: 'derivedLayer')]
    #[ORM\JoinColumn(name: 'layer_original_id', referencedColumnName: 'layer_id')]
    private ?Layer $originalLayer = null;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 1])]
    private ?int $layerActive = 1;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 1])]
    private ?int $layerSelectable = 1;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 0])]
    private ?int $layerActiveOnStart = 0;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 1])]
    private ?int $layerToggleable = 1;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 1])]
    private ?int $layerEditable = 1;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 125, options: ['default' => ''])]
    private ?string $layerName = '';

    #[Groups(['read'])]
    #[ORM\Column(name: 'layer_geotype', length: 255, nullable: true, enumType: LayerGeoType::class)]
    private ?LayerGeoType $layerGeoType = null;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 75, options: ['default' => ''])]
    private ?string $layerShort = '';

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 75, options: ['default' => ''])]
    private ?string $layerGroup = '';

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 512, options: ['default' => ''])]
    private ?string $layerTooltip = '';

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 75, options: ['default' => 'management'])]
    private ?string $layerCategory = 'management';

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 75, options: ['default' => 'aquaculture'])]
    private ?string $layerSubcategory = 'aquaculture';

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 75, options: ['default' => 'Miscellaneous'])]
    private ?string $layerKpiCategory = 'Miscellaneous';

    #[Groups(['read'])]
    #[ORM\Column(type: 'json_document', nullable: true)]
    private mixed $layerType = null;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::SMALLINT, length: 3, options: ['default' => 1])]
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
        length: 1024,
        nullable: false,
        options: ['default' => '{}', 'json_encode_options' => JSON_FORCE_OBJECT]
    )]
    private array $layerTextInfo = [];

    #[Groups(['read'])]
    #[ORM\Column(
        type: 'json_document',
        length: 255,
        nullable: true,
        options: [
            'default' => '[{"state":"ASSEMBLY","time":2},{"state":"ACTIVE","time":10},{"state":"DISMANTLE","time":2}]'
        ]
    )]
    private array $layerStates = [
        ['state' => 'ASSEMBLY', 'time' => 2],
        ['state' => 'ACTIVE', 'time' => 10],
        ['state' => 'DISMANTLE', 'time' => 2],
    ];

    #[Groups(['read'])]
    #[ORM\Column(type: 'json_document', length: 512, nullable: true)]
    private mixed $layerRaster = null;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::FLOAT, options: ['default' => 100])]
    private ?float $layerLastupdate = 100;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::SMALLINT, length: 4, options: ['default' => 0])]
    private ?int $layerMelupdate = 0;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $layerEditingType = null;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 512, options: ['default' => 'Default'])]
    private ?string $layerSpecialEntityType = 'Default';

    #[Groups(['read'])]
    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 0])]
    private ?int $layerGreen = 0;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 0])]
    private ?int $layerMelupdateConstruction = 0;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0])]
    private ?float $layerFilecreationtime = 0;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $layerMedia = null;

    #[Groups(['read'])]
    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $layerEntityValueMax = null;

    #[Groups(['read'])]
    #[ORM\Column(type: 'json_document', length: 1024, nullable: true)]
    private mixed $layerTags = null;

    #[ORM\OneToMany(mappedBy: 'layer', targetEntity: Geometry::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $geometry;

    #[ORM\OneToMany(mappedBy: 'restrictionStartLayer', targetEntity: Restriction::class, cascade: ['persist'])]
    private Collection $restrictionStart;

    #[ORM\OneToMany(mappedBy: 'restrictionEndLayer', targetEntity: Restriction::class, cascade: ['persist'])]
    private Collection $restrictionEnd;

    #[ORM\ManyToMany(targetEntity: Layer::class, mappedBy: 'pressureGeneratingLayer', cascade: ['persist'])]
    private Collection $pressure;

    #[ORM\JoinTable(name: 'mel_layer')]
    #[ORM\JoinColumn(name: 'mel_layer_pressurelayer', referencedColumnName: 'layer_id')]
    #[ORM\InverseJoinColumn(name: 'mel_layer_layer_id', referencedColumnName: 'layer_id')]
    #[ORM\ManyToMany(targetEntity: Layer::class, inversedBy: 'pressure', cascade: ['persist'])]
    private Collection $pressureGeneratingLayer;

    #[ORM\OneToMany(mappedBy: 'layer', targetEntity: PlanLayer::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $planLayer;

    #[ORM\OneToMany(mappedBy: 'layer', targetEntity: PlanDelete::class, cascade: ['persist'])]
    private Collection $planDelete;

    #[ORM\OneToMany(mappedBy: 'layer', targetEntity: PlanRestrictionArea::class, cascade: ['persist'])]
    private Collection $planRestrictionArea;

    private bool $layerGeometryWithGeneratedMspids = false;

    private ?bool $layerDownloadFromGeoserver = null;

    private ?string $layerPropertyAsType = null;

    private ?int $layerWidth = null;

    private ?int $layerHeight = null;

    private ?string $layerRasterMaterial = null;

    private ?bool $layerRasterFilterMode = null;

    private ?bool $layerRasterColorInterpolation = null;

    private ?string $layerRasterPattern = null;

    private ?float $layerRasterMinimumValueCutoff = null;

    private ?string $layerRasterURL = null;

    private ?array $layerRasterBoundingbox = null;

    public function __construct()
    {
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

    public function getLayerRasterMaterial(): ?string
    {
        return $this->layerRasterMaterial;
    }

    public function setLayerRasterMaterial(?string $layerRasterMaterial): static
    {
        $this->layerRasterMaterial = $layerRasterMaterial;
        return $this;
    }

    public function getLayerRasterFilterMode(): ?bool
    {
        return $this->layerRasterFilterMode;
    }

    public function setLayerRasterFilterMode(?bool $layerRasterFilterMode): static
    {
        $this->layerRasterFilterMode = $layerRasterFilterMode;
        return $this;
    }

    public function getLayerRasterColorInterpolation(): ?bool
    {
        return $this->layerRasterColorInterpolation;
    }

    public function setLayerRasterColorInterpolation(?bool $layerRasterColorInterpolation): static
    {
        $this->layerRasterColorInterpolation = $layerRasterColorInterpolation;
        return $this;
    }

    public function getLayerRasterPattern(): ?string
    {
        return $this->layerRasterPattern;
    }

    public function setLayerRasterPattern(?string $layerRasterPattern): static
    {
        $this->layerRasterPattern = $layerRasterPattern;
        return $this;
    }

    public function getLayerRasterMinimumValueCutoff(): ?float
    {
        return $this->layerRasterMinimumValueCutoff;
    }

    public function setLayerRasterMinimumValueCutoff(?float $layerRasterMinimumValueCutoff): static
    {
        $this->layerRasterMinimumValueCutoff = $layerRasterMinimumValueCutoff;
        return $this;
    }

    public function getLayerRasterURL(): ?string
    {
        return $this->layerRasterURL;
    }

    public function setLayerRasterURL(?string $layerRasterURL): static
    {
        $this->layerRasterURL = $layerRasterURL;
        return $this;
    }

    public function getLayerRasterBoundingbox(): ?array
    {
        return $this->layerRasterBoundingbox;
    }

    public function setLayerRasterBoundingbox(?array $layerRasterBoundingbox): static
    {
        $this->layerRasterBoundingbox = $layerRasterBoundingbox;
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

    public function getLayerTextInfo(): array
    {
        return $this->layerTextInfo;
    }

    public function setLayerTextInfo(array $layerTextInfo): static
    {
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

    public function getLayerRaster(): mixed
    {
        $this->setLayerRasterMaterial($this->layerRaster["layer_raster_material"] ?? null);
        $this->setLayerRasterPattern($this->layerRaster["layer_raster_pattern"] ?? null);
        $this->setLayerRasterMinimumValueCutoff($this->layerRaster["layer_raster_minimum_value_cutoff"] ?? null);
        $this->setLayerRasterColorInterpolation($this->layerRaster["layer_raster_color_interpolation"] ?? null);
        $this->setLayerRasterFilterMode($this->layerRaster["layer_raster_filter_mode"] ?? null);
        $this->setLayerRasterURL($this->layerRaster["url"] ?? null);
        $this->setLayerRasterBoundingbox($this->layerRaster["boundingbox"] ?? null);
        return $this->layerRaster;
    }

    public function getLayerRasterAsJson(): ?string
    {
        return json_encode($this->layerRaster);
    }

    public function setLayerRaster(mixed $layerRaster = null): static
    {
        $layerRaster ??= [];
        $layerRaster["layer_raster_material"] = $this->getLayerRasterMaterial();
        $layerRaster["layer_raster_pattern"] = $this->getLayerRasterPattern();
        $layerRaster["layer_raster_minimum_value_cutoff"] = $this->getLayerRasterMinimumValueCutoff();
        $layerRaster["layer_raster_color_interpolation"] = $this->getLayerRasterColorInterpolation();
        $layerRaster["layer_raster_filter_mode"] = $this->getLayerRasterFilterMode();
        $layerRaster["url"] = $this->getLayerRasterURL();
        $layerRaster["boundingbox"] = $this->getLayerRasterBoundingbox();
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
}
