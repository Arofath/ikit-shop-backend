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
        'product_name', // Field ថ្មី
        'product_sku',  // Field ថ្មី
        'quantity',
        'unit_price',
        'subtotal',     // ផ្អែកតាម Migration ចាស់របស់អ្នកគឺ quantity * unit_price
    ];

    // ==========================================
    // 🌟 ការកំណត់ Relationships
    // ==========================================

    /**
     * Item នេះស្ថិតនៅក្នុង Order ណាមួយ
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Item នេះសំដៅទៅលើ Product មួយ
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
