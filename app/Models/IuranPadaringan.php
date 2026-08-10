<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IuranPadaringan extends Model
{
    protected $guarded = [];
    protected $casts = ['months' => 'array', 'monthDates' => 'array'];

    //
}
