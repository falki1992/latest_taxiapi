<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Driver extends Authenticatable
{
    use HasFactory, HasApiTokens;
    protected $fillable = [
        'uuid',
        'first_name',
        'last_name',
        'mobile_no',
        'address',
        'dob',
        'email',
        'user_type',
        'user_id',
        'car_type',
        'vehicle_type_id',
        'mobile_no',
        'email',
        'user_type',
        'avatar',
        'password',
        'status',
        'car_id',
        'no_plate',
        'no_plate_firstname',
        'production_year',
        'is_online',
        'gender',
        'country_code',
        'driver_type_id',
        'role_id',
        'car_year',
        'wallet',
        'gst_amount_to_pay'

    ];
    protected $hidden = [
        'password',

    ];

    public function car()
    {
        return $this->hasOne(Car::class, 'id', 'car_id');
    }

    public function driverCarType()
    {
        return $this->hasOne(CarType::class, 'id', 'car_type');
    }

    public function driverVehicle()
    {
        return $this->hasOne(DriverVehicleType::class, 'id', 'vehicle_type_id');
    }
    public function driverProofs()
    {
        return $this->belongsTo(DriverProofs::class, 'id', 'driver_id');
    }


    public function driverType()
    {
        return $this->belongsTo(DriverCarType::class, 'driver_type_id', 'id');
    }

    public function getWalletAttribute($value)
    {
        return number_format($value, 2, '.', '');
    }

    public function getGstAmountToPayAttribute($value) // Corrected spelling
    {
        return number_format($value, 2, '.', '');
    }

}