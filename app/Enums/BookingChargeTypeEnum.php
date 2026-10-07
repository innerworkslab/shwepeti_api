<?php

namespace App\Enums;

enum BookingChargeTypeEnum: string
{
    case Day = 'day';
    case Session = 'session';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
