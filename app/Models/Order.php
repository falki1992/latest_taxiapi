<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
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
        'comments',
        'user_id',
        'car_type',
        'status',
        'request_screen_shot',
        'distance',
        'user_type',
        'parcel_image','gst_amount','remaining_amount' // Ensure this is included if you want to use it
    ];

    protected $appends = ['screenshot'];

    public function getScreenshotAttribute()
    {
        $filename = $this->request_screen_shot;
        $url = $filename ? asset($filename) : null;
        return $url;
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }
    public function driver()
    {
        return $this->hasOne(User::class, 'id', 'driver_id');
    }


    public function getGstAmountAttribute($value) // Corrected spelling
    {
        return number_format($value, 2, '.', '');
    }

    public function getRemainingAmountAttribute($value) // Corrected spelling
    {
        return number_format($value, 2, '.', '');
    }


    public function getAmountAttribute($value) // Corrected spelling
    {
        return number_format($value, 2, '.', '');
    }


    public function getFromLatAttribute($value) // Corrected spelling
    {
        return number_format($value, 2, '.', '');
    }

    public function getFromLngAttribute($value) // Corrected spelling
    {
        return number_format($value, 2, '.', '');
    }

    public function getToLatAttribute($value) // Corrected spelling
    {
        return number_format($value, 2, '.', '');
    }

    public function getToLngAttribute($value) // Corrected spelling
    {
        return number_format($value, 2, '.', '');
    }

}
