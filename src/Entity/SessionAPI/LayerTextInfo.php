<?php

namespace App\Entity\SessionAPI;

#[\AllowDynamicProperties]
class LayerTextInfo
{
    public ?array $property_per_state = null;
    public ?string $text_color = null;
    public ?string $text_size = null;
    public ?float $zoom_cutoff = null;
    public ?float $x = null;
    public ?float $y = null;
    public ?float $z = null;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if ($key == 'property_per_state' && is_object($value)) {
                $value = get_object_vars($value);
            }
            $this->$key = $value;
        }
    }
}
