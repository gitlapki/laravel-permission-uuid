<?php

namespace Spatie\Permission\Exceptions;

use InvalidArgumentException;

class PermissionDoesNotExist extends InvalidArgumentException
{
    public static function create(string $permissionCode, string $guardName = '')
    {
        return new static("There is no permission code `{$permissionCode}` for guard `{$guardName}`.");
    }

    public static function withUuid(int $permissionUuid, string $guardName = '')
    {
        return new static("There is no [permission] with uuid `{$permissionUuid}` for guard `{$guardName}`.");
    }
}
