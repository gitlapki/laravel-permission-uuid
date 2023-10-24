<?php

namespace Spatie\Permission\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Contracts\Role as RoleContract;
use Symfony\Component\Console\Helper\TableCell;

class Show extends Command
{
    protected $signature = 'rbac:show
            {guard? : The name of the guard}
            {style? : The display style (default|borderless|compact|box)}';

    protected $description = 'Show a table of roles and permissions per guard';

    public function handle()
    {
        $permissionClass = app(PermissionContract::class);
        $roleClass = app(RoleContract::class);
        $permissionPrimaryKey = (new $permissionClass)->getKeyName();
        $style = $this->argument('style') ?? 'default';
        $guard = $this->argument('guard');

        if ($guard) {
            $guards = Collection::make([$guard]);
        } else {
            $guards = $permissionClass::pluck('guard_name')->merge($roleClass::pluck('guard_name'))->unique();
        }

        foreach ($guards as $guard) {
            $this->info("Guard: $guard");



            $roles = $roleClass::whereGuardName($guard)
                ->with('permissions')
                ->orderBy('code')->get()->mapWithKeys(function ($role) use ($permissionPrimaryKey) {
                    return [$role->code => ['permissions' => $role->permissions->pluck($permissionPrimaryKey)]];
                });

            $permissions = $permissionClass::whereGuardName($guard)->orderBy('code')->pluck('code', $permissionPrimaryKey);

            $body = $permissions->map(function ($permission, $id) use ($roles) {
                return $roles->map(function (array $role_data) use ($id) {
                    return $role_data['permissions']->contains($id) ? ' ✔' : ' ·';
                })->prepend($permission);
            });

            $this->table(
                array_merge([
                    $roles->keys()->map(function ($val) {
                        $name = explode('_', $val);
                        array_pop($name);

                        return implode('_', $name);
                    })->prepend('')->toArray(),
                ]),
                $body->toArray(),
                $style
            );
        }
    }
}
