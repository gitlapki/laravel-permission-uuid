<?php

namespace Spatie\Permission\Exceptions;

use InvalidArgumentException;

class RoleDoesNotExist extends InvalidArgumentException
{
    public static function codeOrUuid(string $roleCode, string $guardName = '')
    {
        return new static("There is no role named or uuid `{$roleCode}` for guard `{$guardName}`.");
    }
}
