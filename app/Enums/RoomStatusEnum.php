<?php

namespace App\Enums;

enum RoomStatusEnum: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case Reserved = 'reserved';
    case Dirty = 'dirty';
    case Maintenance = 'maintenance';
    case Cleaning = 'cleaning';
    case OutOfService = 'out_of_service';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
