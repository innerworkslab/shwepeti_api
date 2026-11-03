<?php

namespace Database\Seeders;

use App\Enums\UserPortalAccessEnum;
use App\Enums\UserRoleEnum;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(PermissionSeeder::class);
        $permissions = app(PermissionCatalog::class)->names();

        User::query()->updateOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'Hotel Administrator',
            'password' => 'password',
            'role' => UserRoleEnum::HotelAdministrator->value,
            'portal_access' => UserPortalAccessEnum::Admin->value,
            'is_active' => true,
        ])->givePermissionTo($permissions);

        User::query()->updateOrCreate([
            'email' => 'pos@example.com',
        ], [
            'name' => 'POS Staff',
            'password' => 'password',
            'role' => UserRoleEnum::RestaurantAdministrator->value,
            'portal_access' => UserPortalAccessEnum::Pos->value,
            'is_active' => true,
        ])->givePermissionTo($permissions);

        User::query()->updateOrCreate([
            'email' => 'manager@example.com',
        ], [
            'name' => 'Manager',
            'password' => 'password',
            'role' => UserRoleEnum::HotelAdministrator->value,
            'portal_access' => UserPortalAccessEnum::Both->value,
            'is_active' => true,
        ])->givePermissionTo($permissions);
    }
}
