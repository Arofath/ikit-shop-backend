<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'order_number',
        'user_id',
        'created_by',
        'address_id',

        'shipping_name',
        'shipping_phone',
        'shipping_address',
        'shipping_zone_id',

        'base_shipping_cost',
        'bulky_surcharge_total',
        'subtotal',
        'discount_total',
        'shipping_fee',
        'tax_amount',
        'grand_total',

        'currency',

        'status',
        'payment_status',
        'payment_method',
        'payment_expires_at',

        'payment_receipt',
        'payment_note',
        'note',
    ];

    protected $casts = [
        'base_shipping_cost' => 'decimal:2',
        'bulky_surcharge_total' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'grand_total' => 'decimal:2',

        'payment_expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusUpdater()
    {
        return $this->belongsTo(User::class, 'status_updated_by');
    }

    public function paymentProcessor()
    {
        return $this->belongsTo(User::class, 'payment_processed_by');
    }

    public function shippingZone()
    {
        return $this->belongsTo(
            ShippingZone::class,
            'shipping_zone_id'
        );
    }
}
