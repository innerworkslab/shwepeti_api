<?php

namespace Database\Seeders;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate([
            'email' => 'admin@example.com',
        ], [
            'name' => 'Hotel Administrator',
            'password' => 'password',
            'role' => UserRoleEnum::HotelAdministrator->value,
            'is_active' => true,
        ]);

        $this->call([
            UnitSeeder::class,
            ItemCategorySeeder::class,
        ]);
    }
}
