<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FreightOrder extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'from_lat',
        'from_lng',
        'to_lat',
        'to_lng',
        'amount',
        'passengers',
        'amount',
        'comments',
        'user_id',
        'car_type',
        'status',
        'pickup_datetime','other_options','screen_shot','driver_id'
    ];
}
