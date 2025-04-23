<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    use HasFactory;

    protected $table = 'drivers_rating';
    protected $fillable = [
        'customer_id',
        'driver_id',
        'rate',
        'comment','remark'

    ];
    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id'); // Customer references User via customer_id
    }
    
    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id'); // Driver references User via driver_id
    }

}
