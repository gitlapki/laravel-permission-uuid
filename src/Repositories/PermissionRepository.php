<?php

namespace Spatie\Permission\Repositories;

use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Permission;

class PermissionRepository implements PermissionRepositoryInterface
{
    protected string $permissionClass;

    public function __construct()
    {
        $this->permissionClass = config('rbac.models.permission');
    }

    public function getAll(bool $withRoles = true): Collection
    {
        return $this->getPermissionClass()::select()->with('roles')->get();
    }

    public function createNewInstance(): Permission
    {
        return new $this->permissionClass();
    }
}
