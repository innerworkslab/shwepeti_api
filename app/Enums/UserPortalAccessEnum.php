<?php

namespace App\Enums;

enum UserPortalAccessEnum: string
{
    case Admin = 'admin';
    case Pos = 'pos';
    case Both = 'both';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
