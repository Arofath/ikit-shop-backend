<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'product_id',
    ];

    // ទំនាក់ទំនង៖ Favorite នេះជារបស់ User ណាមួយ
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ទំនាក់ទំនង៖ Favorite នេះតំណាងឱ្យ Product ណាមួយ
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
