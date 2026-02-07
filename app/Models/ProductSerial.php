<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;


class ProductSerial extends Model
{
    use HasFactory, HasUuids;

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

    // មុខងារសម្រាប់គណនាថ្ងៃផុតកំណត់ (Expiry Date)

    public function soldMovement()
    {
        return $this->belongsTo(ProductStockMovement::class, 'sold_movement_id');
    }

    /**
     * មុខងារគណនាថ្ងៃផុតកំណត់ធានា
     */
    public function getWarrantyExpiryDateAttribute()
    {
        // បើមិនទាន់លក់ចេញ គឺមិនទាន់មានថ្ងៃផុតកំណត់ទេ
        if (!$this->sold_movement_id || !$this->product->warranty) {
            return null;
        }

        // ថ្ងៃលក់ចេញ + រយៈពេលធានា (ខែ)
        $saleDate = $this->soldMovement->created_at;
        $duration = $this->product->warranty->duration_months;

        return $saleDate->addMonths($duration);
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
