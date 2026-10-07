<?php

namespace App\Enums;

enum PaymentMethodEnum: string
{
    case Cash = 'cash';
    case KPay = 'kpay';
    case WavePay = 'wavepay';
    case BankTransfer = 'bank_transfer';
    case Card = 'card';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
