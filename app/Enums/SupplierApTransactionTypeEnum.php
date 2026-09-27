<?php

namespace App\Enums;

enum SupplierApTransactionTypeEnum: string
{
    case Purchase = 'purchase';
    case Payment = 'payment';
}
