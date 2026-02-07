<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\ProductStockMovement;
use App\Models\ProductSerial;
use Illuminate\Support\Str;


class StockSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();
        $supplier = Supplier::first();

        foreach ($products as $product) {
            $qty = rand(5, 10); // បញ្ចូលស្តុកចៃដន្យចន្លោះពី ៥ ទៅ ១០ គ្រឿង

            // ១. បង្កើតចលនាស្តុក (Stock Movement IN)
            $movement = ProductStockMovement::create([
                'product_id'       => $product->id,
                'supplier_id'      => $supplier->id,
                'type'             => 'IN',
                'quantity'         => $qty,
                'cost_price'       => $product->price * 0.8, // តម្លៃដើមទាបជាងតម្លៃលក់ ២០%
                'balance_after'    => $qty,
                'reference_number' => 'PO-' . strtoupper(Str::random(6)),
                'note'             => 'Initial stock seeding',
            ]);

            // ២. បង្កើតលេខស៊េរី (Serial Numbers) ទៅតាមចំនួន Quantity
            for ($i = 1; $i <= $qty; $i++) {
                ProductSerial::create([
                    'product_id'          => $product->id,
                    'initial_movement_id' => $movement->id,
                    'serial_number'       => strtoupper(substr($product->name, 0, 3)) . '-' . rand(100000, 999999),
                    'status'              => 'AVAILABLE',
                ]);
            }
        }
    }
}
