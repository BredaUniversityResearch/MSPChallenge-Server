<?php

namespace App\Domain\Common\EntityEnums;

enum LayerGeoType: string
{
    case POLYGON = 'polygon';
    case POINT = 'point';
    case RASTER = 'raster';
    case LINE = 'line';
}
