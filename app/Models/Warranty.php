<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warranty extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'duration_months',
        'type',
        'description',
        'is_active',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'is_active'       => 'boolean',
    ];

    /**
     * ទំនាក់ទំនងជាមួយ Product
     * (Product មួយអាចមានកិច្ចសន្យាធានាមួយ)
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
