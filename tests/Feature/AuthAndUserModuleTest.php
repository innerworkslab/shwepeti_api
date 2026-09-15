<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndUserModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_in_an_active_staff_user_and_returns_a_bearer_token(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => UserRoleEnum::HotelAdministrator->value,
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_it_allows_a_hotel_administrator_to_create_update_delete_and_restore_users(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => UserRoleEnum::HotelAdministrator->value,
        ]);

        $token = $this->postJson('/api/v1/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->json('data.token');

        $headers = ['Authorization' => "Bearer {$token}"];

        $created = $this->withHeaders($headers)->postJson('/api/v1/admin/users', [
            'name' => 'Front Office',
            'email' => 'front@example.com',
            'password' => 'password',
            'role' => UserRoleEnum::FrontOfficeAdministrator->value,
            'is_active' => true,
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.email', 'front@example.com')
            ->assertJsonPath('data.created_by', $admin->id);

        $userId = $created->json('data.id');

        $this->withHeaders($headers)->postJson('/api/v1/admin/users', [
            'id' => $userId,
            'name' => 'Front Office Updated',
            'email' => 'front@example.com',
            'role' => UserRoleEnum::FrontOfficeAdministrator->value,
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Front Office Updated')
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.updated_by', $admin->id);

        $this->withHeaders($headers)->deleteJson("/api/v1/admin/users/{$userId}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('users', ['id' => $userId]);

        $this->withHeaders($headers)->postJson("/api/v1/admin/users/{$userId}/restore")
            ->assertOk()
            ->assertJsonPath('data.deleted_by', null);

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'deleted_at' => null,
        ]);
    }

    public function test_it_blocks_non_hotel_administrators_from_user_management(): void
    {
        $frontOffice = User::factory()->create([
            'email' => 'front@example.com',
            'password' => 'password',
            'role' => UserRoleEnum::FrontOfficeAdministrator->value,
        ]);

        $token = $this->postJson('/api/v1/admin/login', [
            'email' => $frontOffice->email,
            'password' => 'password',
        ])->json('data.token');

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/admin/users')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }
}
