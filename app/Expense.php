<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    const STATUS_CANCEL = 0;
    const STATUS_ACTIVO = 1;
}
