<?php

namespace Dashed\DashedTernair\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Dashed\DashedTernair\DashedTernair
 */
class DashedTernair extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'dashed-ternair';
    }
}
