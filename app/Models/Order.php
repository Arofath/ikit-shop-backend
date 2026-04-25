<?php

namespace App\Models;

use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    // 🌟 កំណត់ Field ដែលអនុញ្ញាតឱ្យបញ្ចូលទិន្នន័យ (Mass Assignment)
    protected $fillable = [
        'order_number',
        'user_id',
        'address_id',
        'subtotal',
        'discount_total',
        'shipping_fee',   // Field ថ្មី
        'tax_amount',     // Field ថ្មី
        'grand_total',
        'status',
        'payment_status',
        'payment_method',
        'note',
    ];

    // ==========================================
    // 🌟 ការកំណត់ Relationships
    // ==========================================

    /**
     * Order មួយជារបស់ User (អ្នកទិញ) តែម្នាក់គត់
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Order មួយមានទំនិញ (Items) ច្រើនមុខ
     */
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Order មួយមានអាសយដ្ឋានដឹកជញ្ជូនតែមួយ
     */
    public function address()
    {
        // បើអ្នកមាន Address Model អាចបើកកូដនេះបាន
        // return $this->belongsTo(Address::class);
    }
}
