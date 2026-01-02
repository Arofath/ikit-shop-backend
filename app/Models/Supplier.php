<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Supplier extends Model
{
    use HasFactory, HasUuids;

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
}
