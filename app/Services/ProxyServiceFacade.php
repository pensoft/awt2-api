<?php

namespace App\Services;

use Illuminate\Support\Facades\Facade;

/**
 * @method static createProxy(\Illuminate\Http\Request $request)
 */
class ProxyServiceFacade extends Facade {

    protected static function getFacadeAccessor(){
        return "ProxyService";
    }
}
