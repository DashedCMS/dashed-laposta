<?php

namespace Dashed\DashedLaposta\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Dashed\DashedLaposta\DashedLaposta
 */
class DashedLaposta extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'dashed-laposta';
    }
}
