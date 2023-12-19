<?php

namespace Spatie\Permission\Repositories;

use Spatie\Permission\Contracts\Role;

class RoleRepository implements RoleRepositoryInterface
{
    protected string $roleClass;

    public function __construct()
    {
        $this->roleClass = config('rbac.models.role');
    }
    
    public function createNewInstance(): Role
    {
        return new $this->roleClass();
    }
}
