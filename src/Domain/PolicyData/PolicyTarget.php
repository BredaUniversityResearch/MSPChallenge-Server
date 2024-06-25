<?php

namespace App\Domain\PolicyData;

enum PolicyTarget: string
{
    case PLAN = 'plan';
    case GEOMETRY = 'geometry';
}
