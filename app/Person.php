<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    const TYPE_EMPLOY = "EMPLOY";
    const TYPE_USER = "USER";
    const TYPE_CLIENT = "CLIENT";

    const STATUS_ACTIVE = 1;

    protected $table = 'persons';
}
