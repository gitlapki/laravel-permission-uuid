<?php

namespace Spatie\Permission\Services;

interface CacheServiceInterface
{
    public function flush(): bool;

    public function hydrateRolesCache(Role $roleInstance);
}
