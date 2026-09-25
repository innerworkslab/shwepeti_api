<?php

namespace App\Enums;

enum CashbookTransactionTypeEnum: string
{
    case Income = 'income';
    case Expense = 'expense';
}
