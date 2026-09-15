<?php

namespace App\Enums;

enum RoomBedTypeEnum: string
{
    case KingBed = 'king_bed';
    case QueenBed = 'queen_bed';
    case TwinBed = 'twin_bed';
    case SingleBed = 'single_bed';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::KingBed => 'King Bed',
            self::QueenBed => 'Queen Bed',
            self::TwinBed => 'Twin Bed',
            self::SingleBed => 'Single Bed',
        };
    }
}
