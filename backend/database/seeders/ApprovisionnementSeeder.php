<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Stock;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use Illuminate\Support\Facades\Hash;

class ApprovisionnementSeeder extends Seeder
{
    public function run(): void
    {
        // Recuperer ou creer un tenant de demo
        $tenant = Tenant::first();
        
        if (!$tenant) {
            $tenant = Tenant::create([
                'name' => 'Entreprise Demo',
                'slug' => 'demo',
                'currency' => 'XOF',
                'country' => 'BJ',
                'status' => 'active',
                'mode_pos' => 'B',
                'business_type' => 'retail',
            ]);
        }

        // Creer les entrepots
        $warehouseGros = Warehouse::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'GROS'],
            [
                'name' => 'Magasin Gros',
                'type' => 'gros',
                'location' => 'Zone industrielle',
                'is_active' => true,
            ]
        );

        $warehouseDetail = Warehouse::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'DETAIL'],
            [
                'name' => 'Magasin Detail',
                'type' => 'detail',
                'location' => 'Centre ville',
                'is_active' => true,
            ]
        );

        // Creer des fournisseurs
        $suppliers = [
            ['name' => 'Fournisseur Alpha', 'email' => 'alpha@fournisseur.com', 'phone' => '+229 97 00 00 01', 'payment_terms' => '30 jours', 'lead_time_days' => 7],
            ['name' => 'Fournisseur Beta', 'email' => 'beta@fournisseur.com', 'phone' => '+229 97 00 00 02', 'payment_terms' => '15 jours', 'lead_time_days' => 3],
            ['name' => 'Fournisseur Gamma', 'email' => 'gamma@fournisseur.com', 'phone' => '+229 97 00 00 03', 'payment_terms' => 'Comptant', 'lead_time_days' => 1],
        ];

        foreach ($suppliers as $supplierData) {
            Supplier::firstOrCreate(
                ['tenant_id' => $tenant->id, 'email' => $supplierData['email']],
                array_merge($supplierData, ['tenant_id' => $tenant->id, 'status' => 'active', 'is_active' => true])
            );
        }

        // Creer des produits de demo
        $products = [
            ['name' => 'Riz 25kg', 'code' => 'RIZ-25', 'purchase_price' => 15000, 'selling_price' => 18000, 'unit' => 'sac'],
            ['name' => 'Huile 5L', 'code' => 'HUI-5L', 'purchase_price' => 5000, 'selling_price' => 6500, 'unit' => 'bidon'],
            ['name' => 'Sucre 50kg', 'code' => 'SUC-50', 'purchase_price' => 25000, 'selling_price' => 30000, 'unit' => 'sac'],
            ['name' => 'Lait en poudre 400g', 'code' => 'LAI-400', 'purchase_price' => 2500, 'selling_price' => 3200, 'unit' => 'boite'],
            ['name' => 'Pates 500g', 'code' => 'PAT-500', 'purchase_price' => 800, 'selling_price' => 1200, 'unit' => 'paquet'],
            ['name' => 'Tomate concentree 400g', 'code' => 'TOM-400', 'purchase_price' => 600, 'selling_price' => 900, 'unit' => 'boite'],
            ['name' => 'Savon 200g', 'code' => 'SAV-200', 'purchase_price' => 300, 'selling_price' => 500, 'unit' => 'piece'],
            ['name' => 'Eau minerale 1.5L', 'code' => 'EAU-1.5', 'purchase_price' => 250, 'selling_price' => 400, 'unit' => 'bouteille'],
        ];

        $createdProducts = [];
        foreach ($products as $productData) {
            $product = Product::firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $productData['code']],
                array_merge($productData, [
                    'tenant_id' => $tenant->id,
                    'track_stock' => true,
                    'status' => 'active',
                    'min_stock' => 10,
                ])
            );
            $createdProducts[] = $product;
        }

        // Creer du stock initial dans le magasin gros
        foreach ($createdProducts as $product) {
            $qtyGros = rand(50, 200);
            Stock::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'warehouse' => 'gros',
                ],
                [
                    'warehouse_id' => $warehouseGros->id,
                    'quantity' => $qtyGros,
                    'cost_average' => $product->purchase_price,
                    'unit_cost' => $product->purchase_price,
                    'reserved' => 0,
                    'available' => $qtyGros,
                ]
            );

            // Stock plus faible dans le detail
            $qtyDetail = rand(10, 50);
            Stock::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'warehouse' => 'detail',
                ],
                [
                    'warehouse_id' => $warehouseDetail->id,
                    'quantity' => $qtyDetail,
                    'cost_average' => $product->purchase_price,
                    'unit_cost' => $product->purchase_price,
                    'reserved' => 0,
                    'available' => $qtyDetail,
                ]
            );
        }

        // Recuperer un utilisateur existant ou en creer un
        $user = User::where('tenant_id', $tenant->id)->first();
        if (!$user) {
            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => 'Gerant Demo',
                'email' => 'gerant@demo.com',
                'password' => Hash::make('password123'),
                'role' => 'manager',
            ]);
        }

        // Creer une commande d'achat de demo
        $supplier = Supplier::where('tenant_id', $tenant->id)->first();
        
        $purchase = Purchase::firstOrCreate(
            ['reference' => 'PO-DEMO-001'],
            [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'warehouse_id' => $warehouseGros->id,
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'status' => 'ordered',
                'expected_date' => now()->addDays(3),
                'subtotal' => 0,
                'total' => 0,
            ]
        );

        if ($purchase->wasRecentlyCreated) {
            $subtotal = 0;
            foreach (array_slice($createdProducts, 0, 3) as $product) {
                $qty = rand(10, 30);
                $price = $product->purchase_price;
                
                PurchaseItem::create([
                    'tenant_id' => $tenant->id,
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'quantity_ordered' => $qty,
                    'unit_price' => $price,
                    'line_subtotal' => $qty * $price,
                    'line_total' => $qty * $price,
                ]);
                
                $subtotal += $qty * $price;
            }
            $purchase->update(['subtotal' => $subtotal, 'total' => $subtotal]);
        }

        // Creer une demande de stock de demo
        $stockRequest = StockRequest::firstOrCreate(
            ['reference' => 'REQ-DEMO-001'],
            [
                'tenant_id' => $tenant->id,
                'from_warehouse_id' => $warehouseDetail->id,
                'to_warehouse_id' => $warehouseGros->id,
                'requested_by' => $user->id,
                'status' => 'requested',
                'priority' => 'normal',
                'needed_by_date' => now()->addDays(2),
                'requested_at' => now(),
            ]
        );

        if ($stockRequest->wasRecentlyCreated) {
            foreach (array_slice($createdProducts, 2, 3) as $product) {
                StockRequestItem::create([
                    'stock_request_id' => $stockRequest->id,
                    'product_id' => $product->id,
                    'quantity_requested' => rand(5, 15),
                ]);
            }
        }

        // Creer un transfert de demo
        $transfer = Transfer::firstOrCreate(
            ['reference' => 'TX-DEMO-001'],
            [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'from_warehouse' => 'gros',
                'to_warehouse' => 'detail',
                'from_warehouse_id' => $warehouseGros->id,
                'to_warehouse_id' => $warehouseDetail->id,
                'status' => 'executed',
                'requested_by' => $user->id,
                'requested_at' => now()->subDays(1),
                'executed_at' => now(),
                'total_items' => 0,
            ]
        );

        if ($transfer->wasRecentlyCreated) {
            $totalItems = 0;
            foreach (array_slice($createdProducts, 0, 2) as $product) {
                $qty = rand(5, 10);
                TransferItem::create([
                    'tenant_id' => $tenant->id,
                    'transfer_id' => $transfer->id,
                    'product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_cost' => $product->purchase_price,
                ]);
                $totalItems += $qty;
            }
            $transfer->update(['total_items' => $totalItems]);
        }

        $this->command->info('Donnees de demonstration Approvisionnement creees avec succes!');
        $this->command->info("- Tenant: {$tenant->name}");
        $this->command->info("- Entrepots: Gros (ID: {$warehouseGros->id}), Detail (ID: {$warehouseDetail->id})");
        $this->command->info("- Produits: " . count($createdProducts));
        $this->command->info("- Fournisseurs: " . count($suppliers));
        $this->command->info("- Commande achat: {$purchase->reference}");
        $this->command->info("- Demande stock: {$stockRequest->reference}");
        $this->command->info("- Transfert: {$transfer->reference}");
    }
}
