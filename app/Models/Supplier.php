<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'status',
    ];
    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function stockMovements()
    {
        return $this->hasMany(ProductStockMovement::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_stock_movements')
            ->withPivot(['type', 'quantity', 'cost_price', 'created_at'])
            ->wherePivot('type', 'IN')
            ->distinct();
    }
}
