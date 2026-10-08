<?php

namespace App\Enums;

enum FoodOrderStatusEnum: string
{
    case Pending = 'pending';
    case Preparing = 'preparing';
    case Served = 'served';
    case Cancelled = 'cancelled';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
