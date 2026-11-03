<?php

namespace App\Support;

class PermissionCatalog
{
    /** @return list<array{group: string, label: string, permissions: list<array{name: string, label: string}>}> */
    public function groups(): array
    {
        return json_decode(file_get_contents(database_path('seeders/permissions.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return list<string> */
    public function names(): array
    {
        return collect($this->groups())->pluck('permissions')->flatten(1)->pluck('name')->all();
    }
}
