<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductSpec extends Model
{
    use HasUuids, HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'product_id',
        'spec_group',
        'spec_key',
        'spec_value',
        'sort_order',
    ];

    // រាល់ពេល Spec ប្រែប្រួល Product ក៏ Update updated_at ដែរ
    protected $touches = ['product'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
