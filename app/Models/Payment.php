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
        'currency',
        'payment_method',
        'payment_proof',
        'status',
        'md5',
        'transaction_reference',
        'transaction_hash',
        'from_account_id',
        'to_account_id',
        'external_ref',

        'paid_at',
        'expires_at',
        'last_checked_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
