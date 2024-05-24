<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BravoPriceCalendar extends Model
{
    use HasFactory;
    protected $table = "bravo_price_calendar";

    protected $guarded = ['id'];
}
