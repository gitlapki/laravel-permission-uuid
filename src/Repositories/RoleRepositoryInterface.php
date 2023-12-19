<?php

namespace Spatie\Permission\Repositories;

use Spatie\Permission\Contracts\Role;

interface RoleRepositoryInterface
{
    public function createNewInstance(): Role;
}
