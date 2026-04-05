<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ProductSerial extends Model
{
    use HasFactory, HasUuids;

    // 🌟 បន្ថែមចំណុចនេះឱ្យដូច Model ផ្សេងទៀត
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'product_id',
        'initial_movement_id',
        'sold_movement_id',
        'serial_number',
        'status',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovement()
    {
        return $this->belongsTo(ProductStockMovement::class, 'initial_movement_id');
    }

    public function soldMovement()
    {
        return $this->belongsTo(ProductStockMovement::class, 'sold_movement_id');
    }

    /**
     * មុខងារគណនាថ្ងៃផុតកំណត់ធានា
     */
    public function getWarrantyExpiryDateAttribute()
    {
        if (!$this->sold_movement_id || !$this->product->warranty) {
            return null;
        }

        $saleDate = $this->soldMovement->created_at;
        $duration = $this->product->warranty->duration_months;

        // 🌟 ប្រើ copy() ដើម្បីកុំឱ្យប៉ះពាល់ដល់ថ្ងៃដើមរបស់ soldMovement
        return $saleDate->copy()->addMonths($duration);
    }

    /**
     * ឆែកថាតើនៅមានធានា (Active) ឬផុតកំណត់ (Expired)
     */
    public function getWarrantyStatusAttribute()
    {
        $expiryDate = $this->warranty_expiry_date;

        if (!$expiryDate) return 'No Warranty Record';

        return now()->lessThanOrEqualTo($expiryDate) ? 'Active' : 'Expired';
    }
}
