<?php

namespace Spatie\Permission\Repositories;

use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Permission;

interface PermissionRepositoryInterface
{
    public function getAll(bool $withRoles = true): Collection;

    public function createNewInstance(): Permission;
}
