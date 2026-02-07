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
        'slug',
        'category_id',
        'brand_id',
        'warranty_id',
        'product_series_id',
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

    // ទាញយក Thumbnail មកជាមួយជានិច្ច
    protected $with = ['thumbnail'];

    // Accessor សម្រាប់បង្ហាញតម្លៃបញ្ចុះរួច (Optional but useful)
    public function getFinalPriceAttribute()
    {
        if (!$this->discount_percent) return $this->price;
        return $this->price - ($this->price * ($this->discount_percent / 100));
    }

    // --- Relationships ---
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
    public function warranty()
    {
        return $this->belongsTo(Warranty::class);
    }
    public function productSeries()
    {
        return $this->belongsTo(ProductSeries::class);
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
    // បន្ថែម 'current_stock' ទៅក្នុង append ដើម្បីឱ្យវារត់ស្វ័យប្រវត្តិពេលហៅ Product
    protected $appends = ['current_stock'];
    // មុខងារគណនាចំនួនស្តុកសរុបនាពេលបច្ចុប្បន្ន (Sum IN + Sum ADJUST - Sum OUT)
    public function getCurrentStockAttribute()
    {
        // ប្រើ Query តែមួយដើម្បីបូកសរុប (More Efficient)
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

    public function availableSerials() // ទាញយកតែ Serial ណាដែលនៅទំនេរ (AVAILABLE) សម្រាប់លក់
    {
        return $this->serials()->where('status', 'AVAILABLE');
    }

    // Relationship ពិសេសសម្រាប់រូបភាពតំណាង
    public function thumbnail()
    {
        return $this->hasOne(ProductImage::class)->where('is_thumbnail', true);
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
