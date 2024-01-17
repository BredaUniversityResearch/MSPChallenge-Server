<?php

namespace App\Entity;

use App\Repository\LayerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

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

    public function getLayerType(): ?string
    {
        return $this->layerType;
    }

    public function setLayerType(?string $layerType): Layer
    {
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

    public function setLayerInfoProperties(?string $layerInfoProperties): Layer
    {
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

    public function setLayerStates(?string $layerStates): Layer
    {
        $this->layerStates = $layerStates;
        return $this;
    }

    public function getLayerRaster(): ?string
    {
        return $this->layerRaster;
    }

    public function setLayerRaster(?string $layerRaster): Layer
    {
        $this->layerRaster = $layerRaster;
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
}
