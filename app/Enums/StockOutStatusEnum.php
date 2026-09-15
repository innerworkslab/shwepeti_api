<?php

namespace App\Enums;

enum StockOutStatusEnum: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';
}
