<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CityToCityOrders extends Model
{
    use HasFactory;
    protected $table="city_to_city_orders";
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
        'departure'
    ];

}
