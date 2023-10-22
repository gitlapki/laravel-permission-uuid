<?php

namespace Spatie\Permission\Exceptions;

use InvalidArgumentException;

class PermissionAlreadyExists extends InvalidArgumentException
{
    public static function create(string $permissionCode, string $guardName)
    {
        return new static("A `{$permissionCode}` permission already exists for guard `{$guardName}`.");
    }
}
