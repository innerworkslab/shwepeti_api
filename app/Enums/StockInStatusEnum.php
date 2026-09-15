<?php

namespace App\Enums;

enum StockInStatusEnum: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';
}
