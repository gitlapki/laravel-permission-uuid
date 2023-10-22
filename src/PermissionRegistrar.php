<?php

namespace Spatie\Permission;

use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Contracts\Role;

class PermissionRegistrar
{
    protected Repository $cacheStore;
    protected CacheManager $cacheManager;
    protected string $cacheKey;
    protected int $cacheExpirationTime = 60 * 60 * 24;
    protected string $permissionClass;
    protected string $roleClass;
    protected Collection|array|null $permissions;
    private array $cachedRoles = [];
    private array $alias = [];
    private array $except = [];

    public function __construct(CacheManager $cacheManager)
    {
        $this->cacheManager = $cacheManager;
        $this->cacheStore = $this->getCacheStoreFromConfig();
        $this->cacheExpirationTime = config('rbac.cache.expiration_time') ?: $this->cacheExpirationTime;
        $this->cacheKey = config('rbac.cache.key');
        $this->permissionClass = config('rbac.models.permission');
        $this->roleClass = config('rbac.models.role');
    }

    protected function getCacheStoreFromConfig(): Repository
    {
        // the 'default' fallback here is from the rbac.php config file,
        // where 'default' means to use config(cache.default)
        $cacheDriver = config('rbac.cache.store', 'default');

        // when 'default' is specified, no action is required since we already have the default instance
        if ($cacheDriver === 'default') {
            return $this->cacheManager->store();
        }

        // if an undefined cache store is specified, fallback to 'array' which is Laravel's closest equiv to 'none'
        if (!\array_key_exists($cacheDriver, config('cache.stores'))) {
            $cacheDriver = 'array';
        }

        return $this->cacheManager->store($cacheDriver);
    }

    /**
     * Register the permission check method on the gate.
     * We resolve the Gate fresh here, for benefit of long-running instances.
     */
    public function registerPermissions(): bool
    {
        app(Gate::class)->before(function (Authorizable $user, string $ability) {
            if (method_exists($user, 'checkPermissionTo')) {
                return $user->checkPermissionTo($ability) ?: null;
            }
        });

        return true;
    }

    /**
     * Flush the cache.
     */
    public function forgetCachedPermissions()
    {
        $this->permissions = null;

        return $this->cacheStore->forget($this->cacheKey);
    }

    /**
     * Clear class permissions.
     * This is only intended to be called by the PermissionServiceProvider on boot,
     * so that long-running instances like Swoole don't keep old data in memory.
     */
    public function clearClassPermissions()
    {
        $this->permissions = null;
    }

    /**
     * Load permissions from cache
     * This get cache and turns array into \Illuminate\Database\Eloquent\Collection
     */
    private function loadPermissions()
    {
        if ($this->permissions) {
            return;
        }

        $this->permissions = $this->cacheStore->remember(
            key: $this->cacheKey,
            ttl: $this->cacheExpirationTime,
            callback: function () {
                return $this->getSerializedPermissionsForCache();
            }
        );

        // fallback for old cache method, must be removed on next major version
        if (!isset($this->permissions['alias'])) {
            $this->forgetCachedPermissions();
            $this->loadPermissions();

            return;
        }

        $this->alias = $this->permissions['alias'];

        $this->hydrateRolesCache();

        $this->permissions = $this->getHydratedPermissionCollection();

        $this->cachedRoles = $this->alias = $this->except = [];
    }

    /**
     * Get the permissions based on the passed params.
     */
    public function getPermissions(array $params = [], bool $onlyOne = false): Collection
    {
        $this->loadPermissions();

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

    /**
     * Get an instance of the permission class.
     */
    public function getPermissionClass(): string
    {
        return $this->permissionClass;
    }

    public function setPermissionClass($permissionClass)
    {
        $this->permissionClass = $permissionClass;

        return $this;
    }

    /**
     * Get an instance of the role class.
     */
    public function getRoleClass(): Role
    {
        return app($this->roleClass);
    }

    public function setRoleClass($roleClass)
    {
        $this->roleClass = $roleClass;
        config()->set('rbac.models.role', $roleClass);
        app()->bind(Role::class, $roleClass);

        return $this;
    }

    public function getCacheRepository(): Repository
    {
        return $this->cacheStore;
    }

    public function getCacheStore(): Store
    {
        return $this->cacheStore->getStore();
    }

    protected function getPermissionsWithRoles(): Collection
    {
        return $this->getPermissionClass()::select()->with('roles')->get();
    }

    /**
     * Changes array keys with alias
     */
    private function aliasedArray($model): array
    {
        return collect(is_array($model) ? $model : $model->getAttributes())->except($this->except)
            ->keyBy(function ($value, $key) {
                return $this->alias[$key] ?? $key;
            })->all();
    }

    /**
     * Array for cache alias
     */
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

    /*
     * Make the cache smaller using an array with only required fields
     */
    private function getSerializedPermissionsForCache()
    {
        $this->except = config(key: 'rbac.cache.column_names_except', default: ['created_at', 'updated_at', 'deleted_at']);

        $permissions = $this->getPermissionsWithRoles()
            ->map(function ($permission) {
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

    private function hydrateRolesCache()
    {
        $roleClass = $this->getRoleClass();
        $roleInstance = new $roleClass();

        array_map(function ($item) use ($roleInstance) {
            $role = $roleInstance->newFromBuilder($this->aliasedArray($item));
            $this->cachedRoles[$role->getKey()] = $role;
        }, $this->permissions['roles']);

        $this->permissions['roles'] = [];
    }
}
