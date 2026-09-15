<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['audit.console' => true]);
    }

    public function test_it_retrieves_audit_logs_with_filters(): void
    {
        $headers = $this->authHeaders();

        $created = $this->withHeaders($headers)->postJson('/api/v1/admin/unit-groups', [
            'name' => 'Weight',
            'is_active' => true,
        ]);

        $created->assertCreated();

        $unitGroupId = $created->json('data.id');

        $this->withHeaders($headers)->postJson('/api/v1/admin/unit-groups', [
            'id' => $unitGroupId,
            'name' => 'Weight Updated',
            'is_active' => false,
        ])->assertOk();

        $response = $this->withHeaders($headers)->getJson("/api/v1/admin/audit-logs?auditable_type=unit_group&auditable_id={$unitGroupId}&page=1");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Audit logs retrieved successfully.')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.auditable_type', 'unit_group')
            ->assertJsonPath('data.0.auditable_id', $unitGroupId);

        $auditLogId = $response->json('data.0.id');

        $this->withHeaders($headers)->getJson("/api/v1/admin/audit-logs/{$auditLogId}")
            ->assertOk()
            ->assertJsonPath('data.id', $auditLogId);
    }

    public function test_it_does_not_store_hidden_user_values_in_audit_log(): void
    {
        $headers = $this->authHeaders();

        $created = $this->withHeaders($headers)->postJson('/api/v1/admin/users', [
            'name' => 'Front Office',
            'email' => 'front@example.com',
            'password' => 'password',
            'role' => UserRoleEnum::FrontOfficeAdministrator->value,
            'is_active' => true,
        ]);

        $created->assertCreated();

        $response = $this->withHeaders($headers)->getJson('/api/v1/admin/audit-logs?auditable_type=user&auditable_id='.$created->json('data.id').'&event=created&page=1');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissingPath('data.0.new_values.password')
            ->assertJsonMissingPath('data.0.new_values.remember_token');
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
