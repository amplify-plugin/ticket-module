<?php

namespace Amplify\System\Ticket\Facades;

use Illuminate\Support\Facades\Facade;

class Ticket extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'tkt';
    }
}
