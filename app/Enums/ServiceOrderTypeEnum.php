<?php

namespace App\Enums;

enum ServiceOrderTypeEnum: string
{
    case Amenity = 'amenity';
    case Laundry = 'laundry';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
