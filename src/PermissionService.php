<?php

namespace Spatie\Permission;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Support\Collection;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Contracts\Role;

use Spatie\Permission\Repositories\RoleRepositoryInterface;
use Spatie\Permission\Repositories\PermissionRepositoryInterface;
use Spatie\Permission\Services\CacheServiceInterface;

class PermissionService
{
    protected string $permissionClass;
    protected string $roleClass;


    private array $alias = [];
    private array $except = [];

    public function __construct(
        private RoleRepositoryInterface $roleRepository,
        private PermissionRepositoryInterface $permissionRepository,
        private CacheServiceInterface $cacheService,
    ) {
        $this->permissionClass = config('rbac.models.permission');
        $this->roleClass = config('rbac.models.role');
    }

    public function forgetCachedPermissions()
    {
        return $this->cacheService->flush();
    }

    public function getPermissions(array $params = [], bool $onlyOne = false): Collection
    {
        $permissions = $this->permissionRepository->getAll(withRoles: true);

        $method = $onlyOne ? 'first' : 'filter';

        $permissions = $this->permissions->$method(static function ($permission) use ($params) {
            foreach ($params as $attr => $value) {
                if ($permission->getAttribute($attr) != $value) {
                    return false;
                }
            }

            return true;
        });

        if ($onlyOne) {
            $permissions = new Collection($permissions ? [$permissions] : []);
        }

        return $permissions;
    }
}
