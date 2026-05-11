<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'receiver_name',
        'receiver_phone',
        'address_detail',
        'city',
        'is_default',
    ];

    // Address នេះជារបស់ User មួយណា?
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
