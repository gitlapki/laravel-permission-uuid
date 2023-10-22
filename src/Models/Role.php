<?php

namespace Spatie\Permission\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Exceptions\GuardDoesNotMatch;
use Spatie\Permission\Exceptions\RoleAlreadyExists;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Guard;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\RefreshesPermissionCache;

/**
 * @property string $uuid
 * @property string $code
 * @property string $guard_name
 * @property string|null $description
 * @property ?\Illuminate\Support\Carbon $created_at
 * @property ?\Illuminate\Support\Carbon $updated_at
 */
class Role extends Model implements RoleContract
{
    use HasUuids;
    use HasPermissions;
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
        return config(key: 'permission.table_names.roles', default: parent::getTable());
    }

    public static function create(array $attributes = [])
    {
        $attributes['guard_name'] = $attributes['guard_name'] ?? Guard::getDefaultName(class: static::class);

        $params = ['code' => $attributes['code'], 'guard_name' => $attributes['guard_name']];

        if (static::findByParam($params)) {
            throw RoleAlreadyExists::create(roleCode: $attributes['code'], guardName: $attributes['guard_name']);
        }

        return static::query()->create($attributes);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            related: config('permission.models.permission'),
            table: config('permission.table_names.role_has_permissions'),
            foreignPivotKey: config('permission.column_names.role_pivot_key'),
            relatedPivotKey: config('permission.column_names.permission_pivot_key')
        );
    }

    public function users(): BelongsToMany
    {
        return $this->morphedByMany(
            related: getModelForGuard($this->attributes['guard_name'] ?? config('auth.defaults.guard')),
            name: 'model',
            table: config('permission.table_names.model_has_roles'),
            foreignPivotKey: config('permission.column_names.role_pivot_key'),
            relatedPivotKey: config('permission.column_names.model_morph_key')
        );
    }


    public static function findByUuidOrCode(string $codeOrUuid, $guardName = null): RoleContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);

        $role = static::where(function ($query) use ($codeOrUuid) {
            $query->where('code', '=', $codeOrUuid)->orWhere('uuid', '=', $codeOrUuid);
        })->where('guard_name', '=', $guardName)->first();

        if (!$role) {
            throw RoleDoesNotExist::codeOrUuid(roleCode: $codeOrUuid, guardName: $guardName);
        }

        return $role;
    }

    public static function findOrCreate(string $code, $guardName = null): RoleContract
    {
        $guardName = $guardName ?? Guard::getDefaultName(static::class);

        $role = static::findByParam(['code' => $code, 'guard_name' => $guardName]);

        if (!$role) {
            return static::create(
                [
                    'code' => $code,
                    'guard_name' => $guardName
                ]
            );
        }

        return $role;
    }

    protected static function findByParam(array $params = [])
    {
        $query = static::query();

        foreach ($params as $key => $value) {
            $query->where($key, $value);
        }

        return $query->first();
    }


    public function hasPermissionTo($permission): bool
    {
        if (config('permission.enable_wildcard_permission', false)) {
            return $this->hasWildcardPermission(permission: $permission, guardName: $this->getDefaultGuardName());
        }

        $permissionClass = $this->getPermissionClass();

        if (is_string($permission)) {
            $permission = $permissionClass->findByUuidOrName($permission, $this->getDefaultGuardName());
        }

        if (!$this->getGuardNames()->contains($permission->guard_name)) {
            throw GuardDoesNotMatch::create($permission->guard_name, $this->getGuardNames());
        }

        return $this->permissions->contains($permission->getKeyName(), $permission->getKey());
    }
}
