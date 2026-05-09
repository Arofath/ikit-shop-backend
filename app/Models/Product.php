<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasUuids, HasFactory, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'sku',
        'description',
        'slug',
        'brand_id',
        'warranty_id',
        'price',
        'cost_price',
        'discount_percent',
        'is_active',
        'is_serialized',
        'is_recommended',
        'sort_order',
        
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'is_active' => 'boolean',
            'is_serialized',
        ];
    }


    // Accessor សម្រាប់បង្ហាញតម្លៃបញ្ចុះរួច (Optional but useful)
    public function getFinalPriceAttribute()
    {
        if (!$this->discount_percent) return $this->price;
        return $this->price - ($this->price * ($this->discount_percent / 100));
    }

    // --- Relationships ---
    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
    public function warranty()
    {
        return $this->belongsTo(Warranty::class);
    }
    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
    public function specs()
    {
        return $this->hasMany(ProductSpec::class);
    }
    public function stockMovements()
    {
        return $this->hasMany(ProductStockMovement::class);
    }

    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'product_stock_movements')
            ->withPivot(['type', 'quantity', 'cost_price', 'created_at'])
            ->wherePivot('type', 'IN') // យកតែប្រវត្តិដែលទិញចូល
            ->distinct();
    }

    // មុខងារគណនាចំនួនស្តុកសរុបនាពេលបច្ចុប្បន្ន 
    // We keep the method, but it won't run automatically anymore.
    public function getCurrentStockAttribute()
    {
        $stats = $this->stockMovements()
            ->selectRaw("
            SUM(CASE WHEN type = 'IN' THEN quantity ELSE 0 END) +
            SUM(CASE WHEN type = 'ADJUST' THEN quantity ELSE 0 END) -
            SUM(CASE WHEN type = 'OUT' THEN quantity ELSE 0 END) as total
        ")
            ->first();

        return (int) ($stats->total ?? 0);
    }

    public function serials()
    {
        return $this->hasMany(ProductSerial::class);
    }

    public function availableSerials()
    {
        return $this->serials()->where('status', 'AVAILABLE');
    }

    public function thumbnail()
    {
        return $this->hasOne(ProductImage::class)->where('is_thumbnail', true);
    }

    // ទំនាក់ទំនង៖ Product មួយអាចស្ថិតនៅក្នុង Cart ជាច្រើន (One-to-Many)
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    // --- Scopes ---
    public function scopeSearch($query, $keyword)
    {
        return $query->when($keyword, function ($q) use ($keyword) {
            $q->where(function ($inner) use ($keyword) {
                $inner->where('name', 'like', "%{$keyword}%")
                    ->orWhere('sku', 'like', "%{$keyword}%");
            });
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
