<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Otp extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'contact_type',
        'contact_value',
        'otp_hash',
        'purpose',
        'expires_at',
        'is_used',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_used' => 'boolean',
            'attempts' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scope
    public function scopeValid($query)
    {
        return $query
            ->where('is_used', false)
            ->where('expires_at', '>', now());
    }

    public function scopePurpose($query, string $purpose)
    {
        return $query->where('purpose', $purpose);
    }

    /* Helpers */
    public function markAsUsed()
    {
        $this->update(['is_used' => true]);
    }
}
