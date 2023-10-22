<?php

namespace Spatie\Permission\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface Permission
{
    public function roles(): BelongsToMany;

    public static function findByUuidOrCode(string $code, ?string $guardName): self;

    public static function findOrCreate(string $code, ?string $guardName): self;
}
