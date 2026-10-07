<?php

namespace Database\Seeders;

use App\Enums\RoomBedTypeEnum;
use App\Enums\RoomStatusEnum;
use App\Models\Room;
use App\Models\RoomCategory;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            [
                'name' => 'Room 101',
                'category' => 'Standard',
                'price' => 45000,
                'status' => RoomStatusEnum::Available->value,
                'beds' => [
                    ['bed_type' => RoomBedTypeEnum::QueenBed->value, 'qty' => 1],
                ],
            ],
            [
                'name' => 'Room 102',
                'category' => 'Standard',
                'price' => 45000,
                'status' => RoomStatusEnum::Available->value,
                'beds' => [
                    ['bed_type' => RoomBedTypeEnum::TwinBed->value, 'qty' => 2],
                ],
            ],
            [
                'name' => 'Room 201',
                'category' => 'Deluxe',
                'price' => 65000,
                'status' => RoomStatusEnum::Available->value,
                'beds' => [
                    ['bed_type' => RoomBedTypeEnum::KingBed->value, 'qty' => 1],
                ],
            ],
            [
                'name' => 'Room 202',
                'category' => 'Deluxe',
                'price' => 65000,
                'status' => RoomStatusEnum::Cleaning->value,
                'beds' => [
                    ['bed_type' => RoomBedTypeEnum::QueenBed->value, 'qty' => 1],
                    ['bed_type' => RoomBedTypeEnum::SingleBed->value, 'qty' => 1],
                ],
            ],
            [
                'name' => 'Room 301',
                'category' => 'Suite',
                'price' => 95000,
                'status' => RoomStatusEnum::Available->value,
                'beds' => [
                    ['bed_type' => RoomBedTypeEnum::KingBed->value, 'qty' => 1],
                    ['bed_type' => RoomBedTypeEnum::SingleBed->value, 'qty' => 2],
                ],
            ],
        ];

        foreach ($rooms as $roomData) {
            $category = RoomCategory::query()->where('name', $roomData['category'])->firstOrFail();

            $room = Room::query()->updateOrCreate([
                'name' => $roomData['name'],
            ], [
                'room_category_id' => $category->id,
                'price' => $roomData['price'],
                'status' => $roomData['status'],
            ]);

            $bedTypes = collect($roomData['beds'])->pluck('bed_type')->all();
            $room->beds()->whereNotIn('bed_type', $bedTypes)->delete();

            foreach ($roomData['beds'] as $bed) {
                $room->beds()->updateOrCreate([
                    'bed_type' => $bed['bed_type'],
                ], [
                    'qty' => $bed['qty'],
                ]);
            }
        }
    }
}
