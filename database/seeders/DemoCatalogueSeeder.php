<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoCatalogueSeeder extends Seeder
{
    /**
     * Run the database seeds. Données de démonstration pour valider le parcours de bout en bout.
     */
    public function run(): void
    {
        $visserie = Category::firstOrCreate(['name' => 'Visserie & fixations']);
        $plomberie = Category::firstOrCreate(['name' => 'Plomberie']);
        $peinture = Category::firstOrCreate(['name' => 'Peinture & droguerie']);
        $electricite = Category::firstOrCreate(['name' => 'Électricité']);

        $quincaGros = Supplier::firstOrCreate(
            ['name' => 'Quincaillerie Gros SARL'],
            ['contact_name' => 'Mamadou Traoré', 'phone' => '+225 07 00 00 00 01', 'lead_time_days' => 5]
        );
        $peintureDistrib = Supplier::firstOrCreate(
            ['name' => 'Peinture Distribution CI'],
            ['contact_name' => 'Awa Koné', 'phone' => '+225 07 00 00 00 02', 'lead_time_days' => 10]
        );

        $products = [
            ['reference' => 'VIS-6X40', 'name' => 'Vis à bois 6x40mm (boîte de 100)', 'category_id' => $visserie->id, 'supplier_id' => $quincaGros->id, 'purchase_price' => 1500, 'sale_price' => 2200, 'unit' => 'boîte', 'barcode' => '3400000000011', 'low_stock_threshold' => 10, 'stock' => 40],
            ['reference' => 'CLOU-70', 'name' => 'Clous acier 70mm (kg)', 'category_id' => $visserie->id, 'supplier_id' => $quincaGros->id, 'purchase_price' => 900, 'sale_price' => 1400, 'unit' => 'kg', 'barcode' => '3400000000028', 'low_stock_threshold' => 15, 'stock' => 60],
            ['reference' => 'TUBE-PVC-100', 'name' => 'Tube PVC évacuation Ø100mm (2m)', 'category_id' => $plomberie->id, 'supplier_id' => $quincaGros->id, 'purchase_price' => 3200, 'sale_price' => 4500, 'unit' => 'unité', 'barcode' => '3400000000035', 'low_stock_threshold' => 8, 'stock' => 25],
            ['reference' => 'ROBINET-STD', 'name' => 'Robinet standard laiton', 'category_id' => $plomberie->id, 'supplier_id' => $quincaGros->id, 'purchase_price' => 4800, 'sale_price' => 7000, 'unit' => 'unité', 'barcode' => '3400000000042', 'low_stock_threshold' => 5, 'stock' => 4],
            ['reference' => 'PEINT-BLC-5L', 'name' => 'Peinture façade blanche 5L', 'category_id' => $peinture->id, 'supplier_id' => $peintureDistrib->id, 'purchase_price' => 8500, 'sale_price' => 12000, 'unit' => 'bidon', 'barcode' => '3400000000059', 'low_stock_threshold' => 6, 'stock' => 18],
            ['reference' => 'CABLE-2.5', 'name' => 'Câble électrique souple 2.5mm² (mètre)', 'category_id' => $electricite->id, 'supplier_id' => $quincaGros->id, 'purchase_price' => 350, 'sale_price' => 550, 'unit' => 'mètre', 'barcode' => '3400000000066', 'low_stock_threshold' => 50, 'stock' => 200],
            ['reference' => 'DISJ-20A', 'name' => 'Disjoncteur 20A', 'category_id' => $electricite->id, 'supplier_id' => $quincaGros->id, 'purchase_price' => 2200, 'sale_price' => 3500, 'unit' => 'unité', 'barcode' => '3400000000073', 'low_stock_threshold' => 5, 'stock' => 3],
        ];

        foreach ($products as $data) {
            $stock = $data['stock'];
            unset($data['stock']);

            $product = Product::firstOrCreate(['reference' => $data['reference']], $data);

            if ($product->wasRecentlyCreated) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => StockMovement::TYPE_ENTREE,
                    'quantity' => $stock,
                    'reason' => 'Stock initial (démonstration)',
                ]);
            }
        }
    }
}
