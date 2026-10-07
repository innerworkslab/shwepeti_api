<?php

namespace App\Enums;

enum BookingPaymentTypeEnum: string
{
    case Deposit = 'deposit';
    case Partial = 'partial';
    case Checkout = 'checkout';
    case Refund = 'refund';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
