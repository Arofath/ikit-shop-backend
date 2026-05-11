<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'order_id',
        'amount',
        'payment_method',
        'payment_proof',
        'status',
        'transaction_reference',
        'paid_at',
    ];

    // Payment នេះសម្រាប់ Order មួយណា?
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
