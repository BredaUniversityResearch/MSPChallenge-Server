<?php

namespace App\Entity\SessionAPI;

#[\AllowDynamicProperties]
class LayerRaster
{
    public ?string $layer_raster_material = null;
    public ?string $layer_raster_pattern = null;
    public ?float $layer_raster_minimum_value_cutoff = null;
    public ?bool $layer_raster_color_interpolation = null;
    public ?bool $layer_raster_filter_mode = null;
    public ?string $url = null;
    public ?array $boundingbox = null;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    public function getLayerRasterMaterial(): ?string
    {
        return $this->layer_raster_material;
    }

    public function setLayerRasterMaterial(?string $layer_raster_material): static
    {
        $this->layer_raster_material = $layer_raster_material;
        return $this;
    }

    public function getLayerRasterPattern(): ?string
    {
        return $this->layer_raster_pattern;
    }

    public function setLayerRasterPattern(?string $layer_raster_pattern): static
    {
        $this->layer_raster_pattern = $layer_raster_pattern;
        return $this;
    }

    public function getLayerRasterMinimumValueCutoff(): ?float
    {
        return $this->layer_raster_minimum_value_cutoff;
    }

    public function setLayerRasterMinimumValueCutoff(?float $layer_raster_minimum_value_cutoff): static
    {
        $this->layer_raster_minimum_value_cutoff = $layer_raster_minimum_value_cutoff;
        return $this;
    }

    public function getLayerRasterColorInterpolation(): ?bool
    {
        return $this->layer_raster_color_interpolation;
    }

    public function setLayerRasterColorInterpolation(?bool $layer_raster_color_interpolation): static
    {
        $this->layer_raster_color_interpolation = $layer_raster_color_interpolation;
        return $this;
    }

    public function getLayerRasterFilterMode(): ?bool
    {
        return $this->layer_raster_filter_mode;
    }

    public function setLayerRasterFilterMode(?bool $layer_raster_filter_mode): static
    {
        $this->layer_raster_filter_mode = $layer_raster_filter_mode;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getBoundingbox(): ?array
    {
        return $this->boundingbox;
    }

    public function setBoundingbox(?array $boundingbox): static
    {
        $this->boundingbox = $boundingbox;
        return $this;
    }
}
