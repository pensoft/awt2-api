<?php

namespace App\Authentication;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;

class User extends GenericUser implements Authenticatable
{

    /**
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->attributes['id'];
    }
}
