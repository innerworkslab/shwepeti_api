<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::transaction(function (): void {
            foreach (app(PermissionCatalog::class)->names() as $name) {
                Permission::findOrCreate($name, 'web');
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
