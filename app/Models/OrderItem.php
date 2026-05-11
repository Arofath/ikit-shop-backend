<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_sku',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    // OrderItem នេះស្ថិតនៅក្នុង Order មួយណា?
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // OrderItem នេះតំណាងឱ្យ Product មួយណា?
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
