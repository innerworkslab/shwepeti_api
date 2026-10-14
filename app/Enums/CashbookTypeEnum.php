<?php

namespace App\Enums;

enum CashbookTypeEnum: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case PosCash = 'pos_cash';
    case PosBank = 'pos_bank';
}
