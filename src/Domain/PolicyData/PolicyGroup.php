<?php

namespace App\Domain\PolicyData;

enum PolicyGroup: string
{
    case POLICY = 'policy';
    case FILTER = 'filter';
}
