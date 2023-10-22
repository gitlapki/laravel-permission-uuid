<?php

namespace Spatie\Permission\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Contracts\Permission as PermissionContract;

class CreatePermission extends Command
{
    protected $signature = 'rbac:permissions-create
                {code : The code of the permission}
                {guard? : The name of the guard}';

    protected $description = 'Create a permission';

    public function handle()
    {
        $permissionClass = app(PermissionContract::class);

        $permission = $permissionClass::findOrCreate($this->argument('code'), $this->argument('guard'));

        $this->info(
            "Permission `{$permission->code}` " . ($permission->wasRecentlyCreated ? 'created' : 'already exists')
        );
    }
}
