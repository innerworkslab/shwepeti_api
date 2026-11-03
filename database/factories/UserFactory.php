<?php

namespace Database\Factories;

use App\Enums\UserPortalAccessEnum;
use App\Enums\UserRoleEnum;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRoleEnum::HotelAdministrator->value,
            'portal_access' => UserPortalAccessEnum::Admin->value,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /** @param list<string>|null $permissions */
    public function withPermissions(?array $permissions = null): static
    {
        return $this->afterCreating(function (User $user) use ($permissions): void {
            $permissionModels = array_map(
                fn (string $name): Permission => Permission::findOrCreate($name, 'web'),
                $permissions ?? app(PermissionCatalog::class)->names(),
            );

            $user->givePermissionTo($permissionModels);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
