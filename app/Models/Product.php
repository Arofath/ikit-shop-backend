<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasUuids, HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'sku',
        'slug',
        'category_id',
        'brand_id',
        'description',
        'price',
        'discount_percent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected $with = ['thumbnail'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(ProductStockMovement::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function thumbnail()
    {
        return $this->hasOne(ProductImage::class)->where('is_thumbnail', true);
    }

    public function specs()
    {
        return $this->hasMany(ProductSpec::class);
    }
    // Optional helper (grouped specs for API):
    public function groupedSpecs()
    {
        return $this->specs->groupBy('spec_group');
    }

    public function currentStock()
    {
        return $this->stockMovements()->sum('quantity');
    }

    // Scope
    public function scopeSearch($query, $keyword)
    {
        if (!$keyword) return $query;

        return $query->where(function ($q) use ($keyword) {
            $q->where('name', 'ilike', "%{$keyword}%")
                ->orWhere('sku', 'ilike', "%{$keyword}%");
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
