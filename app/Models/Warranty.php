<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Warranty extends Model
{
    use HasFactory, HasUuids;

    // 🌟 បន្ថែម ២ បន្ទាត់នេះដាច់ខាតសម្រាប់ UUID
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'duration_months',
        'type',
        'description',
        'is_active',
    ];

    // 🌟 ប្តូរមកប្រើ Method តាមស្តង់ដារ Laravel ថ្មី
    protected function casts(): array
    {
        return [
            'duration_months' => 'integer',
            'is_active'       => 'boolean',
        ];
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
