<?php

namespace Tests\Feature;

use App\Enums\RoomBedTypeEnum;
use App\Enums\RoomStatusEnum;
use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomManagementModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_room_categories_with_one_endpoint(): void
    {
        $headers = $this->authHeaders();

        $created = $this->withHeaders($headers)->postJson('/api/v1/admin/room-categories', [
            'name' => ' Deluxe   Room ',
            'description' => 'Large room',
            'is_active' => true,
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.name', 'Deluxe Room')
            ->assertJsonPath('data.is_active', true);

        $categoryId = $created->json('data.id');

        $this->withHeaders($headers)->postJson('/api/v1/admin/room-categories', [
            'id' => $categoryId,
            'name' => 'Premium Deluxe',
            'description' => null,
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Premium Deluxe')
            ->assertJsonPath('data.is_active', false);

        $this->withHeaders($headers)->getJson('/api/v1/admin/room-categories?is_active=0&page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_it_toggles_room_category_active_state(): void
    {
        $headers = $this->authHeaders();

        $categoryId = $this->withHeaders($headers)->postJson('/api/v1/admin/room-categories', [
            'name' => 'Executive',
            'is_active' => true,
        ])->json('data.id');

        $this->withHeaders($headers)->postJson("/api/v1/admin/room-categories/{$categoryId}/toggle-active", [
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.id', $categoryId)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('room_categories', [
            'id' => $categoryId,
            'is_active' => false,
        ]);
    }

    public function test_it_saves_rooms_and_room_beds_with_one_endpoint(): void
    {
        $headers = $this->authHeaders();

        $categoryId = $this->withHeaders($headers)->postJson('/api/v1/admin/room-categories', [
            'name' => 'Suite',
            'is_active' => true,
        ])->json('data.id');

        $created = $this->withHeaders($headers)->postJson('/api/v1/admin/rooms', [
            'name' => 'Room 101',
            'room_category_id' => $categoryId,
            'price' => 120,
            'status' => RoomStatusEnum::Available->value,
            'room_beds' => [
                ['bed_type' => RoomBedTypeEnum::KingBed->value, 'qty' => 1],
                ['bed_type' => RoomBedTypeEnum::SingleBed->value, 'qty' => 2],
            ],
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.name', 'Room 101')
            ->assertJsonPath('data.room_beds.0.bed_type', RoomBedTypeEnum::KingBed->value)
            ->assertJsonPath('data.room_beds.1.qty', 2);

        $roomId = $created->json('data.id');

        $this->withHeaders($headers)->postJson('/api/v1/admin/rooms', [
            'id' => $roomId,
            'name' => 'Room 101 Updated',
            'room_category_id' => $categoryId,
            'price' => 150,
            'status' => RoomStatusEnum::Cleaning->value,
            'room_beds' => [
                ['bed_type' => RoomBedTypeEnum::KingBed->value, 'qty' => 2],
            ],
        ])->assertOk()
            ->assertJsonPath('data.name', 'Room 101 Updated')
            ->assertJsonPath('data.status', RoomStatusEnum::Cleaning->value)
            ->assertJsonPath('data.room_beds.0.qty', 2)
            ->assertJsonCount(1, 'data.room_beds');

        $this->assertDatabaseMissing('room_beds', [
            'room_id' => $roomId,
            'bed_type' => RoomBedTypeEnum::SingleBed->value,
        ]);
    }

    public function test_it_updates_room_status_with_dedicated_endpoint(): void
    {
        $headers = $this->authHeaders();

        $categoryId = $this->withHeaders($headers)->postJson('/api/v1/admin/room-categories', [
            'name' => 'Standard',
            'is_active' => true,
        ])->json('data.id');

        $roomId = $this->withHeaders($headers)->postJson('/api/v1/admin/rooms', [
            'name' => 'Room 202',
            'room_category_id' => $categoryId,
            'price' => 90,
            'status' => RoomStatusEnum::Available->value,
            'room_beds' => [
                ['bed_type' => RoomBedTypeEnum::QueenBed->value, 'qty' => 1],
            ],
        ])->json('data.id');

        $this->withHeaders($headers)->postJson("/api/v1/admin/rooms/{$roomId}/status", [
            'status' => RoomStatusEnum::Maintenance->value,
        ])->assertOk()
            ->assertJsonPath('data.id', $roomId)
            ->assertJsonPath('data.status', RoomStatusEnum::Maintenance->value)
            ->assertJsonPath('data.room_beds.0.bed_type', RoomBedTypeEnum::QueenBed->value);
    }

    public function test_it_returns_standard_json_when_room_is_not_found(): void
    {
        $this->withHeaders($this->authHeaders())->postJson('/api/v1/admin/rooms/10/status', [
            'status' => RoomStatusEnum::Maintenance->value,
        ])->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Resource not found.');
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        $admin = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => UserRoleEnum::HotelAdministrator->value,
        ]);

        $token = $this->postJson('/api/v1/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->json('data.token');

        return ['Authorization' => "Bearer {$token}"];
    }
}
