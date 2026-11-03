<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

class GrantUserPermissions extends Command
{
    protected $signature = 'permissions:grant {user : Existing user ID} {permissions?* : Permission names} {--all : Grant all catalog permissions}';

    protected $description = 'Grant direct permissions to an existing user without changing their role or portal access';

    public function handle(PermissionCatalog $catalog): int
    {
        $user = User::query()->find($this->argument('user'));
        $names = $this->option('all') ? $catalog->names() : $this->argument('permissions');

        if (! $user || $names === [] || array_diff($names, $catalog->names()) !== []) {
            $this->error('Provide an existing user ID and valid catalog permissions, or --all.');

            return self::FAILURE;
        }

        $permissions = Permission::query()->where('guard_name', 'web')->whereIn('name', $names)->get();

        if ($permissions->count() !== count(array_unique($names))) {
            $this->error('Run PermissionSeeder before granting permissions.');

            return self::FAILURE;
        }

        $user->givePermissionTo($permissions);
        $this->info("Permissions granted to user {$user->id}.");

        return self::SUCCESS;
    }
}
