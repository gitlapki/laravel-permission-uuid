<?php

namespace Spatie\Permission\Exceptions;

use InvalidArgumentException;

class RoleDoesNotExist extends InvalidArgumentException
{
    public static function namedOrUuid(string $roleName, string $guardName = '')
    {
        return new static("There is no role named or uuid `{$roleName}` for guard `{$guardName}`.");
    }
}
