<?php
// app/Models/DriverTransaction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverTransaction extends Model
{
    use HasFactory;

    protected $fillable = ['driver_id', 'balance', 'last_updated','gst_amount'];

    // Define the relationship with the Driver model
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
