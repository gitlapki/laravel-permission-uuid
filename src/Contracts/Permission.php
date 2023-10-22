<?php

namespace Spatie\Permission\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface Permission
{
    /**
     * A permission can be applied to roles.
     */
    public function roles(): BelongsToMany;

    /**
     * Find a permission by its code.
     *
     * @param string|null $guardName
     *
     * @throws \Spatie\Permission\Exceptions\PermissionDoesNotExist
     */
    public static function findByUuidOrCode(string $code, $guardName): self;


    /**
     * Find or Create a permission by its name and guard name.
     *
     * @param string|null $guardName
     */
    public static function findOrCreate(string $code, $guardName): self;
}
