<?php

namespace Spatie\Permission\Services;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Contracts\Permission;

class CacheService implements CacheServiceInterface
{
    private Repository $cacheStore;
    private string $cacheKey;
    private int $cacheExpirationTime = 60 * 60 * 24;
    private array $cachedRoles = [];
    private array $except = [];
    private array $alias = [];

    public function __construct(
        CacheManager $cacheManager,
    ) {
        // the 'default' fallback here is from the rbac.php config file,
        // where 'default' means to use config(cache.default)
        $cacheDriver = config('rbac.cache.store', 'default');

        // when 'default' is specified, no action is required since we already have the default instance
        if ($cacheDriver === 'default') {
            $this->cacheStore = $this->cacheManager->store();
        }

        // if an undefined cache store is specified, fallback to 'array' which is Laravel's closest equiv to 'none'
        if (!\array_key_exists($cacheDriver, config('cache.stores'))) {
            $cacheDriver = 'array';
        }

        $this->cacheStore = $this->cacheManager->store($cacheDriver);

        $this->cacheExpirationTime = config('rbac.cache.expiration_time') ?: $this->cacheExpirationTime;
        $this->cacheKey = config('rbac.cache.key');
    }

    public function flush(): bool
    {
        return $this->cacheStore->forget($this->cacheKey);
    }

    public function remember()
    {
        $this->cacheStore->remember(
            key: $this->cacheKey,
            ttl: $this->cacheExpirationTime,
            callback: function () {
                return $this->getSerializedPermissionsForCache();
            }
        );
    }

    public function getSerializedPermissionsForCache(Collection $permissions)
    {
        $this->except = config(key: 'rbac.cache.column_names_except', default: [
            'created_at',
            'updated_at',
            'deleted_at'
        ]);

        $mappedPermissions = $permissions->map(function (Permission $permission) {
            if (!$this->alias) {
                $this->aliasModelFields($permission);
            }

            return $this->aliasedArray($permission) + $this->getSerializedRoleRelation($permission);
        })->all();

        $roles = array_values($this->cachedRoles);
        $this->cachedRoles = [];

        return ['alias' => array_flip($this->alias)] + compact('permissions', 'roles');
    }

    private function getSerializedRoleRelation($permission)
    {
        if (!$permission->roles->count()) {
            return [];
        }

        if (!isset($this->alias['roles'])) {
            $this->alias['roles'] = 'r';
            $this->aliasModelFields($permission->roles[0]);
        }

        return [
            'r' => $permission->roles->map(function ($role) {
                if (!isset($this->cachedRoles[$role->getKey()])) {
                    $this->cachedRoles[$role->getKey()] = $this->aliasedArray($role);
                }

                return $role->getKey();
            })->all(),
        ];
    }

    private function getHydratedPermissionCollection()
    {
        $permissionClass = $this->getPermissionClass();
        $permissionInstance = new $permissionClass();

        return Collection::make(
            array_map(function ($item) use ($permissionInstance) {
                return $permissionInstance
                    ->newFromBuilder($this->aliasedArray(array_diff_key($item, ['r' => 0])))
                    ->setRelation('roles', $this->getHydratedRoleCollection($item['r'] ?? []));
            }, $this->permissions['permissions'])
        );
    }

    private function getHydratedRoleCollection(array $roles)
    {
        return Collection::make(
            array_values(
                array_intersect_key($this->cachedRoles, array_flip($roles))
            )
        );
    }

    public function hydrateRolesCache(Role $roleInstance, $permissions)
    {
        $roleClass = $this->getRoleClass();
        $roleInstance = new $roleClass();

        array_map(function ($item) use ($roleInstance) {
            $role = $roleInstance->newFromBuilder($this->aliasedArray($item));
            $this->cachedRoles[$role->getKey()] = $role;
        }, $permissions['roles']);

        $permissions['roles'] = [];
        
        return $permissions;
    }

    //todo $newKeys приходит как объект, проверить на strict_types = 1
    private function aliasModelFields($newKeys = []): void
    {
        $i = 0;
        $alphas = !count($this->alias) ? range('a', 'h') : range('j', 'p');

        foreach (array_keys($newKeys->getAttributes()) as $value) {
            if (!isset($this->alias[$value])) {
                $this->alias[$value] = $alphas[$i++] ?? $value;
            }
        }

        $this->alias = array_diff_key($this->alias, array_flip($this->except));
    }

    private function aliasedArray($model): array
    {
        return collect(is_array($model) ? $model : $model->getAttributes())->except($this->except)
            ->keyBy(function ($value, $key) {
                return $this->alias[$key] ?? $key;
            })->all();
    }
}
