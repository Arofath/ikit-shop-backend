<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
    ];

    // ទំនាក់ទំនង៖ Item នេះស្ថិតក្នុង Cart មួយ
    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    // ទំនាក់ទំនង៖ Item នេះតំណាងឱ្យ Product មួយ
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
