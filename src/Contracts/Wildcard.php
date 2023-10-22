<?php

namespace Spatie\Permission\Contracts;

interface Wildcard
{
    public function implies($permission): bool;
}
