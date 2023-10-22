<?php

namespace Spatie\Permission\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface Role
{
    public function permissions(): BelongsToMany;

    public static function findByUuidOrCode(string $codeOrUuid, ?string $guardName): self;

    public static function findOrCreate(string $code, ?string  $guardName): self;

    public function hasPermissionTo($permission): bool;
}
