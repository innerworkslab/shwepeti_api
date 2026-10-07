<?php

namespace App\Enums;

enum BookingTypeEnum: string
{
    case Reservation = 'reservation';
    case WalkIn = 'walk_in';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
