<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserType extends Model
{
    use HasFactory;

   

    public function getStatusAttribute($value)
    {
       
        $status = $this->attributes['status'];
        
       
        return $status === 1 ? 'active' : 'inactive';
    }


}
