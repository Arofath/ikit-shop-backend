<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class ProductSeries extends Model
{
    use HasUuids, HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }


    protected static function booted()
    {
        static::saving(function ($series) {
            if ($series->isDirty('name')) {
                $slug = Str::slug($series->name);
                $count = static::where('slug', 'like', "{$slug}%")
                    ->where('id', '!=', $series->id)
                    ->count();

                $series->slug = $count ? "{$slug}-" . ($count + 1) : $slug;
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
}
