<?php

namespace App\Enums;

enum TransferStatusEnum: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';
}
