<?php

namespace App\Entity;

use App\Repository\LayerRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use function App\await;

#[ORM\Entity(repositoryClass: LayerRepository::class)]

class Layer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, length: 11)]
    private ?int $layerId;

    #[ORM\Column(type: Types::INTEGER, length: 11, nullable: true)]
    private ?int $layerOriginalId;

    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 1])]
    private ?int $layerActive = 1;

    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 1])]
    private ?int $layerSelectable = 1;

    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 0])]
    private ?int $layerActiveOnStart = 0;

    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 1])]
    private ?int $layerToggleable = 1;

    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 1])]
    private ?int $layerEditable = 1;

    #[ORM\Column(type: Types::STRING, length: 125, options: ['default' => ''])]
    private ?string $layerName = '';

    #[ORM\Column(type: Types::STRING, length: 75, options: ['default' => ''])]
    private ?string $layerGeotype = '';

    #[ORM\Column(type: Types::STRING, length: 75, options: ['default' => ''])]
    private ?string $layerShort = '';

    #[ORM\Column(type: Types::STRING, length: 75, options: ['default' => ''])]
    private ?string $layerGroup = '';

    #[ORM\Column(type: Types::STRING, length: 512, options: ['default' => ''])]
    private ?string $layerTooltip = '';

    #[ORM\Column(type: Types::STRING, length: 75, options: ['default' => 'management'])]
    private ?string $layerCategory = 'management';

    #[ORM\Column(type: Types::STRING, length: 75, options: ['default' => 'aquaculture'])]
    private ?string $layerSubcategory = 'aquaculture';

    #[ORM\Column(type: Types::STRING, length: 75, options: ['default' => 'Miscellaneous'])]
    private ?string $layerKpiCategory = 'Miscellaneous';

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $layerType;

    #[ORM\Column(type: Types::SMALLINT, length: 3, options: ['default' => 1])]
    private ?int $layerDepth = 1;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $layerInfoProperties;

    #[ORM\Column(type: Types::STRING, length: 1024, nullable: true)]
    private ?string $layerInformation;

    #[ORM\Column(type: Types::STRING, length: 1024, options: ['default' => '{}'])]
    private ?string $layerTextInfo = '{}';

    #[ORM\Column(
        type: Types::STRING,
        length: 255,
        nullable: true,
        options: [
            'default' => '[{"state":"ASSEMBLY","time":2},{"state":"ACTIVE","time":10},{"state":"DISMANTLE","time":2}]'
        ]
    )]
    private ?string $layerStates =
        '[{"state":"ASSEMBLY","time":2},{"state":"ACTIVE","time":10},{"state":"DISMANTLE","time":2}]';

    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $layerRaster;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 100])]
    private ?float $layerLastupdate = 100;

    #[ORM\Column(type: Types::SMALLINT, length: 4, options: ['default' => 0])]
    private ?int $layerMelupdate = 0;

    #[ORM\Column(type: Types::STRING, length: 512, nullable: true)]
    private ?string $layerEditingType;

    #[ORM\Column(type: Types::STRING, length: 512, options: ['default' => 'Default'])]
    private ?string $layerSpecialEntityType = 'Default';

    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 0])]
    private ?int $layerGreen = 0;

    #[ORM\Column(type: Types::SMALLINT, length: 1, options: ['default' => 0])]
    private ?int $layerMelupdateConstruction = 0;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0])]
    private ?float $layerFilecreationtime = 0;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $layerMedia;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $layerEntityValueMax;

    #[ORM\OneToMany(mappedBy: 'layer', targetEntity: Geometry::class, cascade: ['persist'])]
    private Collection $geometry;

    private ?bool $layerDownloadFromGeoserver;

    private ?string $layerPropertyAsType;

    private ?int $layerWidth;

    private ?int $layerHeight;

    public function getLayerWidth(): ?int
    {
        return $this->layerWidth;
    }

    public function setLayerWidth(?int $layerWidth): Layer
    {
        $this->layerWidth = $layerWidth;
        return $this;
    }

    public function getLayerHeight(): ?int
    {
        return $this->layerHeight;
    }

    public function setLayerHeight(?int $layerHeight): Layer
    {
        $this->layerHeight = $layerHeight;
        return $this;
    }

    private ?string $layerRasterMaterial;

    private ?bool $layerRasterFilterMode;

    private ?bool $layerRasterColorInterpolation;

    private ?string $layerRasterPattern;

    private ?float $layerRasterMinimumValueCutoff;

    public function getLayerRasterMaterial(): ?string
    {
        return $this->layerRasterMaterial;
    }

    public function setLayerRasterMaterial(?string $layerRasterMaterial): Layer
    {
        $this->layerRasterMaterial = $layerRasterMaterial;
        return $this;
    }

    public function getLayerRasterFilterMode(): ?bool
    {
        return $this->layerRasterFilterMode;
    }

    public function setLayerRasterFilterMode(?bool $layerRasterFilterMode): Layer
    {
        $this->layerRasterFilterMode = $layerRasterFilterMode;
        return $this;
    }

    public function getLayerRasterColorInterpolation(): ?bool
    {
        return $this->layerRasterColorInterpolation;
    }

    public function setLayerRasterColorInterpolation(?bool $layerRasterColorInterpolation): Layer
    {
        $this->layerRasterColorInterpolation = $layerRasterColorInterpolation;
        return $this;
    }

    public function getLayerRasterPattern(): ?string
    {
        return $this->layerRasterPattern;
    }

    public function setLayerRasterPattern(?string $layerRasterPattern): Layer
    {
        $this->layerRasterPattern = $layerRasterPattern;
        return $this;
    }

    public function getLayerRasterMinimumValueCutoff(): ?float
    {
        return $this->layerRasterMinimumValueCutoff;
    }

    public function setLayerRasterMinimumValueCutoff(?float $layerRasterMinimumValueCutoff): Layer
    {
        $this->layerRasterMinimumValueCutoff = $layerRasterMinimumValueCutoff;
        return $this;
    }

    public function getLayerDownloadFromGeoserver(): ?bool
    {
        return $this->layerDownloadFromGeoserver;
    }

    public function setLayerDownloadFromGeoserver(?bool $layerDownloadFromGeoserver): Layer
    {
        $this->layerDownloadFromGeoserver = $layerDownloadFromGeoserver;
        return $this;
    }

    public function getLayerPropertyAsType(): ?string
    {
        return $this->layerPropertyAsType;
    }

    public function setLayerPropertyAsType(?string $layerPropertyAsType): Layer
    {
        $this->layerPropertyAsType = $layerPropertyAsType;
        return $this;
    }

    public function getLayerId(): ?int
    {
        return $this->layerId;
    }

    public function setLayerId(?int $layerId): Layer
    {
        $this->layerId = $layerId;
        return $this;
    }

    public function getLayerOriginalId(): ?int
    {
        return $this->layerOriginalId;
    }

    public function setLayerOriginalId(?int $layerOriginalId): Layer
    {
        $this->layerOriginalId = $layerOriginalId;
        return $this;
    }

    public function getLayerActive(): ?int
    {
        return $this->layerActive;
    }

    public function setLayerActive(?int $layerActive): Layer
    {
        $this->layerActive = $layerActive;
        return $this;
    }

    public function getLayerSelectable(): ?int
    {
        return $this->layerSelectable;
    }

    public function setLayerSelectable(?int $layerSelectable): Layer
    {
        $this->layerSelectable = $layerSelectable;
        return $this;
    }

    public function getLayerActiveOnStart(): ?int
    {
        return $this->layerActiveOnStart;
    }

    public function setLayerActiveOnStart(?int $layerActiveOnStart): Layer
    {
        $this->layerActiveOnStart = $layerActiveOnStart;
        return $this;
    }

    public function getLayerToggleable(): ?int
    {
        return $this->layerToggleable;
    }

    public function setLayerToggleable(?int $layerToggleable): Layer
    {
        $this->layerToggleable = $layerToggleable;
        return $this;
    }

    public function getLayerEditable(): ?int
    {
        return $this->layerEditable;
    }

    public function setLayerEditable(?int $layerEditable): Layer
    {
        $this->layerEditable = $layerEditable;
        return $this;
    }

    public function getLayerName(): ?string
    {
        return $this->layerName;
    }

    public function setLayerName(?string $layerName): Layer
    {
        $this->layerName = $layerName;
        return $this;
    }

    public function getLayerGeotype(): ?string
    {
        return $this->layerGeotype;
    }

    public function setLayerGeotype(?string $layerGeotype): Layer
    {
        $this->layerGeotype = $layerGeotype;
        return $this;
    }

    public function getLayerShort(): ?string
    {
        return $this->layerShort;
    }

    public function setLayerShort(?string $layerShort): Layer
    {
        $this->layerShort = $layerShort;
        return $this;
    }

    public function getLayerGroup(): ?string
    {
        return $this->layerGroup;
    }

    public function setLayerGroup(?string $layerGroup): Layer
    {
        $this->layerGroup = $layerGroup;
        return $this;
    }

    public function getLayerTooltip(): ?string
    {
        return $this->layerTooltip;
    }

    public function setLayerTooltip(?string $layerTooltip): Layer
    {
        $this->layerTooltip = $layerTooltip;
        return $this;
    }

    public function getLayerCategory(): ?string
    {
        return $this->layerCategory;
    }

    public function setLayerCategory(?string $layerCategory): Layer
    {
        $this->layerCategory = $layerCategory;
        return $this;
    }

    public function getLayerSubcategory(): ?string
    {
        return $this->layerSubcategory;
    }

    public function setLayerSubcategory(?string $layerSubcategory): Layer
    {
        $this->layerSubcategory = $layerSubcategory;
        return $this;
    }

    public function getLayerKpiCategory(): ?string
    {
        return $this->layerKpiCategory;
    }

    public function setLayerKpiCategory(?string $layerKpiCategory): Layer
    {
        $this->layerKpiCategory = $layerKpiCategory;
        return $this;
    }

    public function getLayerType(): ?array
    {
        return json_decode($this->layerType, true);
    }

    public function setLayerType(string|array|null $layerType): Layer
    {
        if (is_array($layerType)) {
            $layerType = json_encode($layerType);
        }
        $this->layerType = $layerType;
        return $this;
    }

    public function getLayerDepth(): ?int
    {
        return $this->layerDepth;
    }

    public function setLayerDepth(?int $layerDepth): Layer
    {
        $this->layerDepth = $layerDepth;
        return $this;
    }

    public function getLayerInfoProperties(): ?string
    {
        return $this->layerInfoProperties;
    }

    public function setLayerInfoProperties(string|array|null $layerInfoProperties): Layer
    {
        if (is_array($layerInfoProperties)) {
            $layerInfoProperties = json_encode($layerInfoProperties);
        }
        $this->layerInfoProperties = $layerInfoProperties;
        return $this;
    }

    public function getLayerInformation(): ?string
    {
        return $this->layerInformation;
    }

    public function setLayerInformation(?string $layerInformation): Layer
    {
        $this->layerInformation = $layerInformation;
        return $this;
    }

    public function getLayerTextInfo(): ?string
    {
        return $this->layerTextInfo;
    }

    public function setLayerTextInfo(?string $layerTextInfo): Layer
    {
        $this->layerTextInfo = $layerTextInfo;
        return $this;
    }

    public function getLayerStates(): ?string
    {
        return $this->layerStates;
    }

    public function setLayerStates(string|array|null $layerStates): Layer
    {
        if (is_array($layerStates)) {
            $layerStates = json_encode($layerStates);
        }
        $this->layerStates = $layerStates;
        return $this;
    }

    public function getLayerRaster(): ?string
    {
        return $this->layerRaster;
    }

    public function setLayerRaster(string|array|null $layerRaster): Layer
    {
        if (is_string($layerRaster)) {
            $layerRaster = json_decode($layerRaster, true);
            if (!$layerRaster) {
                throw new \Exception('Could not decode $layerRaster. Are you sure it is json?');
            }
        }
        $layerRaster["layer_raster_material"] = $this->getLayerRasterMaterial();
        $layerRaster["layer_raster_pattern"] = $this->getLayerRasterPattern();
        $layerRaster["layer_raster_minimum_value_cutoff"] = $this->getLayerRasterMinimumValueCutoff();
        $layerRaster["layer_raster_color_interpolation"] = $this->getLayerRasterColorInterpolation();
        $layerRaster["layer_raster_filter_mode"] = $this->getLayerRasterFilterMode();

        $this->layerRaster = json_encode($layerRaster);
        return $this;
    }

    public function getLayerLastupdate(): ?float
    {
        return $this->layerLastupdate;
    }

    public function setLayerLastupdate(?float $layerLastupdate): Layer
    {
        $this->layerLastupdate = $layerLastupdate;
        return $this;
    }

    public function getLayerMelupdate(): ?int
    {
        return $this->layerMelupdate;
    }

    public function setLayerMelupdate(?int $layerMelupdate): Layer
    {
        $this->layerMelupdate = $layerMelupdate;
        return $this;
    }

    public function getLayerEditingType(): ?string
    {
        return $this->layerEditingType;
    }

    public function setLayerEditingType(?string $layerEditingType): Layer
    {
        $this->layerEditingType = $layerEditingType;
        return $this;
    }

    public function getLayerSpecialEntityType(): ?string
    {
        return $this->layerSpecialEntityType;
    }

    public function setLayerSpecialEntityType(?string $layerSpecialEntityType): Layer
    {
        $this->layerSpecialEntityType = $layerSpecialEntityType;
        return $this;
    }

    public function getLayerGreen(): ?int
    {
        return $this->layerGreen;
    }

    public function setLayerGreen(?int $layerGreen): Layer
    {
        $this->layerGreen = $layerGreen;
        return $this;
    }

    public function getLayerMelupdateConstruction(): ?int
    {
        return $this->layerMelupdateConstruction;
    }

    public function setLayerMelupdateConstruction(?int $layerMelupdateConstruction): Layer
    {
        $this->layerMelupdateConstruction = $layerMelupdateConstruction;
        return $this;
    }

    public function getLayerFilecreationtime(): ?float
    {
        return $this->layerFilecreationtime;
    }

    public function setLayerFilecreationtime(?float $layerFilecreationtime): Layer
    {
        $this->layerFilecreationtime = $layerFilecreationtime;
        return $this;
    }

    public function getLayerMedia(): ?string
    {
        return $this->layerMedia;
    }

    public function setLayerMedia(?string $layerMedia): Layer
    {
        $this->layerMedia = $layerMedia;
        return $this;
    }

    public function getLayerEntityValueMax(): ?float
    {
        return $this->layerEntityValueMax;
    }

    public function setLayerEntityValueMax(?float $layerEntityValueMax): Layer
    {
        $this->layerEntityValueMax = $layerEntityValueMax;
        return $this;
    }

    public function processLayerMetaData(array $layerMetaData): void
    {
        //these meta vars are to be ignored in the importer (in addition to those that don't exist in the db anyway)
        $ignoreList = [
            "layer_id",
            "layer_name",
            "layer_original_id",
            "layer_raster"
        ];
        $layerColumns = [];
        foreach (await($this->getAsyncDatabase()->queryBySQL("DESCRIBE layer")->then(function (Result $qResult) {
            return $qResult->fetchAllRows();
        })) as $returnedRow) {
            $layerColumns[] = $returnedRow['Field'];
        }
        $layerUpdateArray = [];
        foreach ($layerMetaData as $key => $val) {
            if (!in_array($key, $ignoreList) && in_array($key, $layerColumns)) {
                $layerUpdateArray[$key] = $this->metaValueValidation($key, $val);
            }
        }
        $this->updateRowInTable('layer', $layerUpdateArray, ['layer_id' => $dbLayerId]);
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
        if ($this->geometry->removeElement($geometry)) {
            // set the owning side to null (unless already changed)
            if ($geometry->getLayer() === $this) {
                $geometry->setLayer(null);
            }
        }

        return $this;
    }
}
