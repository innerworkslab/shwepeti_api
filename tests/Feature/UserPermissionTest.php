<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Models\UnitGroup;
use App\Models\User;
use App\Support\PermissionCatalog;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UserPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_seeding_is_repeatable_and_preserves_existing_assignments(): void
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->withPermissions(['rooms.view'])->create();
        Permission::findOrCreate('retired.permission', 'web');
        $this->seed(PermissionSeeder::class);

        $this->assertDatabaseCount('permissions', count(app(PermissionCatalog::class)->names()) + 1);
        $this->assertTrue($user->fresh()->can('rooms.view'));
        $this->assertDatabaseHas('permissions', ['name' => 'retired.permission']);
        $this->assertDatabaseCount('roles', 0);
    }

    public function test_role_alone_grants_nothing_and_direct_permissions_work_for_other_roles(): void
    {
        $this->seed(PermissionSeeder::class);
        $admin = User::factory()->create();
        $this->actingAs($admin)->getJson('/api/v1/admin/rooms')->assertForbidden();
        $user = User::factory()->withPermissions(['rooms.view'])->create(['role' => UserRoleEnum::FrontOfficeAdministrator]);
        $this->actingAs($user)->getJson('/api/v1/admin/rooms')->assertOk();
        $this->postJson('/api/v1/admin/rooms', [])->assertForbidden();
        $this->assertSame([$user->id], User::permission('rooms.view')->pluck('id')->all());
        $this->assertSame([$admin->id], User::withoutPermission('rooms.view')->pluck('id')->all());
        $this->assertSame(['rooms.view'], $user->getAllPermissions()->pluck('name')->all());
    }

    public function test_user_create_and_update_sync_direct_permissions_and_omission_preserves_them(): void
    {
        $this->seed(PermissionSeeder::class);
        $actor = User::factory()->withPermissions(['users.create', 'users.update', 'users.manage_permissions'])->create();
        $payload = ['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'password', 'role' => 'front_office_administrator', 'permissions' => ['rooms.view']];
        $response = $this->actingAs($actor)->postJson('/api/v1/admin/users', $payload)->assertCreated()->assertJsonPath('data.permissions', ['rooms.view']);
        $payload['id'] = $response->json('data.id');
        $payload['permissions'] = ['rooms.update'];
        $this->postJson('/api/v1/admin/users', $payload)->assertOk()->assertJsonPath('data.permissions', ['rooms.update']);
        unset($payload['permissions']);
        $this->postJson('/api/v1/admin/users', $payload)->assertOk()->assertJsonPath('data.permissions', ['rooms.update']);
        $payload['permissions'] = [];
        $this->postJson('/api/v1/admin/users', $payload)->assertOk()->assertJsonPath('data.permissions', []);
        $this->assertFalse(User::findOrFail($payload['id'])->can('rooms.update'));
        $this->assertDatabaseCount('model_has_roles', 0);
    }

    public function test_permission_assignment_requires_separate_permission_and_rejects_unknown_names(): void
    {
        $this->seed(PermissionSeeder::class);
        $actor = User::factory()->withPermissions(['users.create', 'users.update'])->create();
        $payload = ['name' => 'Staff', 'email' => 'staff@example.com', 'password' => 'password', 'role' => 'hotel_administrator', 'permissions' => ['users.manage_permissions']];
        $this->actingAs($actor)->postJson('/api/v1/admin/users', $payload)->assertForbidden()->assertJsonPath('success', false);
        $this->assertDatabaseMissing('users', ['email' => 'staff@example.com']);
        $payload['id'] = $actor->id;
        $payload['email'] = $actor->email;
        $this->postJson('/api/v1/admin/users', $payload)->assertForbidden();
        $actor->givePermissionTo('users.manage_permissions');
        $payload['permissions'] = ['unknown.permission'];
        $this->postJson('/api/v1/admin/users', $payload)->assertUnprocessable()->assertJsonValidationErrors('permissions.0');
        $this->assertFalse($actor->fresh()->can('unknown.permission'));
    }

    public function test_create_permission_cannot_update_via_the_shared_post_endpoint(): void
    {
        $actor = User::factory()->withPermissions(['users.create'])->create();
        $target = User::factory()->create();
        $payload = ['id' => $target->id, 'name' => 'Changed', 'email' => $target->email, 'role' => 'hotel_administrator'];
        $this->actingAs($actor)->postJson('/api/v1/admin/users', $payload)->assertForbidden()->assertJsonPath('success', false);
        $this->assertSame($target->name, $target->fresh()->name);
        $actor->givePermissionTo(Permission::findOrCreate('users.update', 'web'));
        $this->postJson('/api/v1/admin/users', $payload)->assertOk();
        unset($payload['id']);
        $payload['email'] = 'new@example.com';
        $payload['password'] = 'password';
        $actor->revokePermissionTo('users.create');
        $this->postJson('/api/v1/admin/users', $payload)->assertForbidden();
    }

    public function test_toggle_permission_is_independent_of_update_permission(): void
    {
        $group = UnitGroup::query()->create(['name' => 'Weight', 'slug' => 'weight', 'is_active' => true]);
        $actor = User::factory()->withPermissions(['unit_groups.update'])->create();
        $this->actingAs($actor)->postJson("/api/v1/admin/unit-groups/{$group->id}/toggle-active", ['is_active' => false])->assertForbidden();
        $this->assertTrue($group->fresh()->is_active);
        $actor->givePermissionTo(Permission::findOrCreate('unit_groups.toggle_active', 'web'));
        $this->postJson("/api/v1/admin/unit-groups/{$group->id}/toggle-active", ['is_active' => false])->assertOk();
        $this->assertFalse($group->fresh()->is_active);
    }

    public function test_permissions_do_not_bypass_portal_access_and_pos_requires_permissions(): void
    {
        $user = User::factory()->withPermissions(['rooms.view'])->create(['portal_access' => 'admin']);
        $this->actingAs($user)->getJson('/api/v1/pos/rooms')->assertForbidden();
        $user->update(['portal_access' => 'pos']);
        $this->getJson('/api/v1/pos/rooms')->assertOk();
        $user->revokePermissionTo('rooms.view');
        $this->getJson('/api/v1/pos/rooms')->assertForbidden();
    }

    public function test_catalog_requires_management_permission_and_me_returns_direct_permissions(): void
    {
        $this->getJson('/api/v1/admin/permissions')->assertUnauthorized();
        $actor = User::factory()->withPermissions(['rooms.view'])->create();
        $this->actingAs($actor)->getJson('/api/v1/admin/permissions')->assertForbidden();
        $this->getJson('/api/v1/admin/me')->assertOk()->assertJsonPath('data.permissions', ['rooms.view']);
        $actor->givePermissionTo(Permission::findOrCreate('users.manage_permissions', 'web'));
        $this->getJson('/api/v1/admin/permissions')->assertOk()->assertJsonFragment(['name' => 'unit_groups.toggle_active']);
    }

    public function test_every_feature_route_has_a_catalogued_permission(): void
    {
        $names = app(PermissionCatalog::class)->names();
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/') || in_array(basename($route->uri()), ['login', 'logout', 'me'], true)) {
                continue;
            }
            $middleware = array_values(array_filter($route->gatherMiddleware(), fn (string $name): bool => str_starts_with($name, 'feature:')));
            $this->assertNotEmpty($middleware, $route->uri());
            foreach ($middleware as $entry) {
                foreach (explode(',', substr($entry, strlen('feature:'))) as $name) {
                    $this->assertContains($name, $names, $route->uri());
                }
            }
        }
    }

    public function test_operator_can_bootstrap_an_existing_user_without_role_mapping(): void
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create(['role' => UserRoleEnum::InventoryAdministrator]);
        $this->artisan('permissions:grant', ['user' => $user->id, 'permissions' => ['users.manage_permissions', 'users.update']])->assertSuccessful();
        $this->assertTrue($user->fresh()->can('users.manage_permissions'));
        $this->assertFalse($user->fresh()->can('rooms.view'));
        $this->artisan('permissions:grant', ['user' => $user->id, 'permissions' => ['unknown.permission']])->assertFailed();
    }

    public function test_bootstrap_requires_seeded_permissions_and_all_grants_the_catalog(): void
    {
        $user = User::factory()->create();
        $this->artisan('permissions:grant', ['user' => $user->id, '--all' => true])->assertFailed();
        $this->assertDatabaseCount('model_has_permissions', 0);
        $this->seed(PermissionSeeder::class);
        $this->artisan('permissions:grant', ['user' => $user->id, '--all' => true])->assertSuccessful();
        $this->assertEqualsCanonicalizing(app(PermissionCatalog::class)->names(), $user->fresh()->getPermissionNames()->all());
    }

    public function test_user_seeder_grants_all_catalog_permissions_only_to_the_three_seed_accounts(): void
    {
        $ordinaryUser = User::factory()->create();
        $this->seed(UserSeeder::class);
        $permissionNames = app(PermissionCatalog::class)->names();

        foreach (['admin@example.com' => 'admin', 'pos@example.com' => 'pos', 'manager@example.com' => 'both'] as $email => $portal) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $this->assertEqualsCanonicalizing($permissionNames, $user->getPermissionNames()->all());
            $this->assertSame($portal, $user->portal_access->value);
            $user->revokePermissionTo('rooms.view');
        }

        $this->seed(UserSeeder::class);

        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('permissions', count($permissionNames));
        $this->assertDatabaseCount('model_has_permissions', count($permissionNames) * 3);
        $this->assertSame([], $ordinaryUser->fresh()->getPermissionNames()->all());
        foreach (['admin@example.com', 'pos@example.com', 'manager@example.com'] as $email) {
            $this->assertTrue(User::query()->where('email', $email)->firstOrFail()->can('rooms.view'));
        }
        $this->assertDatabaseCount('model_has_roles', 0);
    }
}
