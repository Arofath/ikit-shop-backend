<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // 🌟 ១. ទាញយក HasUuids មកប្រើ

class ShippingZone extends Model
{
    use HasFactory, HasUuids; // 🌟 ២. ប្រកាសប្រើប្រាស់វានៅទីនេះ

    protected $fillable = [
        'name',
        'base_cost',
        'free_shipping_threshold',
        'is_active',
    ];

    protected $casts = [
        'base_cost' => 'decimal:2',
        'free_shipping_threshold' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
