<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'pp_TxnRefNo',
        'driver_id',
        'pp_MerchantID',
        'pp_Password',
        'pp_SecureHash',
        'pp_ResponseCode',
        'pp_ResponseMessage',
        'pp_Status',

    ];

    public function driver()
    {
        return $this->hasOne(User::class, 'id', 'driver_id');
    }

}
