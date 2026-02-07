<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class ProductSeries extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['brand_id', 'name', 'slug', 'description', 'is_active'];

    /**
     * ការកំណត់ Auto-Generate Slug នៅពេល Save
     */
    protected static function booted()
    {
        static::saving(function ($series) {
            // បង្កើត slug តែនៅពេលដែល name មានការប្រែប្រួល ឬ slug នៅទទេ
            if ($series->isDirty('name') || !$series->slug) {
                $slug = Str::slug($series->name);

                // ឆែកមើលក្រែងលោមាន slug ជាន់គ្នា (បើសិនជាមាន វានឹងថែមលេខពីក្រោយ)
                $originalSlug = $slug;
                $count = 1;
                while (static::where('slug', $slug)->where('id', '!=', $series->id)->exists()) {
                    $slug = $originalSlug . '-' . $count++;
                }

                $series->slug = $slug;
            }
        });
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function slideshows()
    {
        return $this->hasMany(Slideshow::class);
    }

    // បន្ថែម Brand Relation បើសិនជាអ្នកមាន Table Brands
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
}