<?php

namespace App\Domain\Common\EntityEnums;

enum PlanState: string
{
    case DESIGN = 'DESIGN';
    case CONSULTATION = 'CONSULTATION';
    case APPROVAL = 'APPROVAL';
    case APPROVED = 'APPROVED';
    case IMPLEMENTED = 'IMPLEMENTED';
    case DELETED = 'DELETED';
}
