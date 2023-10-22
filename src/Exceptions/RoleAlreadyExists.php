<?php

namespace Spatie\Permission\Exceptions;

use InvalidArgumentException;

class RoleAlreadyExists extends InvalidArgumentException
{
    public static function create(string $roleCode, string $guardName)
    {
        return new static("A role `{$roleCode}` already exists for guard `{$guardName}`.");
    }
}
