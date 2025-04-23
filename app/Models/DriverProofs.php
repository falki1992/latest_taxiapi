<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverProofs extends Model
{
    use HasFactory;
    protected $fillable = [
        'driver_id',
        'selfie',
        'selfie_with_nic_licence',
        'nic_front',
        'nic_back',
        'cnic',
        'licence_front',
        'licence_back',
        'licence_expiry_year',
        'vehicle_certificate_front',
        'vehicle_certificate_back'

    ];
}
