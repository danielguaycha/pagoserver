<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    protected $table = 'persons';

    const TYPE_EMPLOY = "EMPLOY";
    const TYPE_USER = "USER";
    const TYPE_CLIENT = "CLIENT";
}
