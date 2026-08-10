<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IuranSampah extends Model
{
    protected $guarded = [];
    protected $casts = ['weeks' => 'array', 'weekDates' => 'array'];

    //
}
