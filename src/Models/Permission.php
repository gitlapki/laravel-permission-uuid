<?php

namespace Spatie\Permission\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Guard;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Traits\RefreshesPermissionCache;

/**
 * @property string $uuid
 * @property string $code
 * @property string $guard_name
 * @property string|null $description
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
class Permission extends Model implements PermissionContract
{
    use HasUuids;
    use HasRoles;
    use RefreshesPermissionCache;

    protected $primaryKey = 'uuid';

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? config('auth.defaults.guard');

        parent::__construct($attributes);

        $this->guarded[] = $this->primaryKey;
    }

    public function getTable()
    {
        return config(key: 'permission.table_names.permissions', default: parent::getTable());
    }

    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? Guard::getDefaultName(static::class);

        $permission = static::getPermission(['code' => $attributes['code'], 'guard_name' => $attributes['guard_name']]);

        if ($permission) {
            throw PermissionAlreadyExists::create(permissionCode: $attributes['code'], guardName: $attributes['guard_name']);
        }

        return static::query()->create($attributes);
    }

    /**
     * A permission can be applied to roles.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            related: config('permission.models.role'),
            table: config('permission.table_names.role_has_permissions'),
            foreignPivotKey: config('permission.column_names.permission_pivot_key'),
            relatedPivotKey: config('permission.column_names.role_pivot_key')
        );
    }

    /**
     * A permission belongs to some users of the model associated with its guard.
     */
    public function users(): BelongsToMany
    {
        return $this->morphedByMany(
            related: getModelForGuard($this->attributes['guard_name'] ?? config('auth.defaults.guard')),
            name: 'model',
            table: config('permission.table_names.model_has_permissions'),
            foreignPivotKey: config('permission.column_names.permission_pivot_key'),
            relatedPivotKey: config('permission.column_names.model_morph_key')
        );
    }

    /**
     * Find a permission by its name (and optionally guardName).
     *
     * @param string|null $guardName
     *
     * @throws \Spatie\Permission\Exceptions\PermissionDoesNotExist
     */
    public static function findByUuidOrCode(string $code, $guardName = null): PermissionContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(class: static::class);
        $permission = static::getPermission(['code' => $code, 'guard_name' => $guardName]);
        if (!$permission) {
            throw PermissionDoesNotExist::create(permissionCode: $code, guardName: $guardName);
        }

        return $permission;
    }


    /**
     * Find or create permission by its name (and optionally guardName).
     *
     * @param string|null $guardName
     */
    public static function findOrCreate(string $code, $guardName = null): PermissionContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(class: static::class);
        $permission = static::getPermission(['code' => $code, 'guard_name' => $guardName]);

        if (!$permission) {
            return static::query()->create(['code' => $code, 'guard_name' => $guardName]);
        }

        return $permission;
    }

    /**
     * Get the current cached permissions.
     */
    protected static function getPermissions(array $params = [], bool $onlyOne = false): Collection
    {
        return app(abstract: PermissionRegistrar::class)
            ->setPermissionClass(static::class)
            ->getPermissions($params, $onlyOne);
    }

    /**
     * Get the current cached first permission.
     *
     * @return \Spatie\Permission\Contracts\Permission
     */
    protected static function getPermission(array $params = []): ?PermissionContract
    {
        return static::getPermissions(params: $params, onlyOne: true)->first();
    }
}
