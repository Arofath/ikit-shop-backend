<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

class ProductSeries extends Model
{
    use HasFactory, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['brand_id', 'name', 'slug', 'description', 'is_active'];

    // 🌟 បន្ថែម Casts ឱ្យប្រាកដថា is_active ជា Boolean ពិតប្រាកដ
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // (កូដ booted, products, slideshows, និង brand រក្សាទុកដដែល ព្រោះវាល្អហើយ)
    protected static function booted()
    {
        static::saving(function ($series) {
            if ($series->isDirty('name') || !$series->slug) {
                $slug = Str::slug($series->name);
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
        return $this->hasMany(Slideshow::class); // សន្មតថាអ្នកមាន Model នេះ
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
}
