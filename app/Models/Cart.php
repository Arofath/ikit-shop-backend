<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Cart extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
    ];

    // ទំនាក់ទំនង៖ Cart មួយជារបស់ User ម្នាក់
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ទំនាក់ទំនង៖ Cart មួយអាចមាន Item ច្រើន
    public function items()
    {
        return $this->hasMany(CartItem::class);
    }
}
