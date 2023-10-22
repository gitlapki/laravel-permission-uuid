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
        return config(key: 'rbac.table_names.permissions', default: parent::getTable());
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

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            related: config('rbac.models.role'),
            table: config('rbac.table_names.role_has_permissions'),
            foreignPivotKey: config('rbac.column_names.permission_pivot_key'),
            relatedPivotKey: config('rbac.column_names.role_pivot_key')
        );
    }

    public function users(): BelongsToMany
    {
        return $this->morphedByMany(
            related: getModelForGuard($this->attributes['guard_name'] ?? config('auth.defaults.guard')),
            name: 'model',
            table: config('rbac.table_names.model_has_permissions'),
            foreignPivotKey: config('rbac.column_names.permission_pivot_key'),
            relatedPivotKey: config('rbac.column_names.model_morph_key')
        );
    }

    public static function findByUuidOrCode(string $code, ?string $guardName = null): PermissionContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(class: static::class);
        $permission = static::getPermission(['code' => $code, 'guard_name' => $guardName]);
        if (!$permission) {
            throw PermissionDoesNotExist::create(permissionCode: $code, guardName: $guardName);
        }

        return $permission;
    }

    public static function findOrCreate(string $code, ?string $guardName = null): PermissionContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(class: static::class);
        $permission = static::getPermission(['code' => $code, 'guard_name' => $guardName]);

        if (!$permission) {
            return static::query()->create(['code' => $code, 'guard_name' => $guardName]);
        }

        return $permission;
    }

    protected static function getPermissions(array $params = [], bool $onlyOne = false): Collection
    {
        return app(abstract: PermissionRegistrar::class)
            ->setPermissionClass(static::class)
            ->getPermissions($params, $onlyOne);
    }

    protected static function getPermission(array $params = []): ?PermissionContract
    {
        return static::getPermissions(params: $params, onlyOne: true)->first();
    }
}
