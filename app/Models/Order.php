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
        'subtotal',
        'discount_total',
        'shipping_fee',
        'tax_amount',
        'grand_total',
        'status',
        'payment_status',
        'payment_method',
        'payment_receipt',
        "payment_note",
        'note',
    ];

    // Order នេះជារបស់ User មួយណា?
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Order នេះមានទំនិញអ្វីខ្លះ?
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    // Order នេះមានការបង់ប្រាក់អ្វីខ្លះ? (ជាទូទៅអាចមានតែ ១)
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
}
