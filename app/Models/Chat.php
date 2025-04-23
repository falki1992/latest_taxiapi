<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use  HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'message','status'


    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    // Get the receiver of the message
    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}
