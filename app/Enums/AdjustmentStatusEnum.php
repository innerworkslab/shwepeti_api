<?php

namespace App\Enums;

enum AdjustmentStatusEnum: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';
}
