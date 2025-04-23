<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverSupport extends Model
{
    protected $fillable = [
        'sender_id', 'receiver_id', 'ticket_no', 'message_type', 'subject', 'message', 'status'
    ];
}
