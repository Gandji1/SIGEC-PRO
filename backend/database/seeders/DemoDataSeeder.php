<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Créer un tenant de démo (Mode B)
        $tenant = Tenant::create([
            'name' => 'Restaurant Africa Demo',
            'slug' => 'restaurant-africa-demo',
            'mode_pos' => 'B',
            'status' => 'active',
            'currency' => 'XOF',
            'country' => 'BJ',
            'accounting_enabled' => true,
            'accounting_setup_complete' => true,
        ]);

        // Créer les warehouses
        Warehouse::create([
            'tenant_id' => $tenant->id,
            'name' => 'Gros Magasin',
            'type' => 'gros',
            'location' => 'Cotonou - Zone Portuaire',
            'capacity' => 10000,
        ]);

        Warehouse::create([
            'tenant_id' => $tenant->id,
            'name' => 'Magasin Détail',
            'type' => 'detail',
            'location' => 'Cotonou - Centre Ville',
            'capacity' => 3000,
        ]);

        Warehouse::create([
            'tenant_id' => $tenant->id,
            'name' => 'Point de Vente',
            'type' => 'pos',
            'location' => 'Cotonou - Avenue Clozel',
            'capacity' => 500,
        ]);

        // Créer l'utilisateur admin
        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Edmond Gandji',
            'email' => 'edmond@restaurantafrica.com',
            'password' => Hash::make('demo123456'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        // Créer un second utilisateur (gérant gros)
        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Alain Tognide',
            'email' => 'alain@restaurantafrica.com',
            'password' => Hash::make('demo123456'),
            'role' => 'warehouse_manager',
            'status' => 'active',
        ]);

        // Créer des fournisseurs
        Supplier::create([
            'tenant_id' => $tenant->id,
            'name' => 'Acme Distribution Bénin',
            'email' => 'contact@acme-benin.com',
            'phone' => '+229 22 45 89 12',
            'address' => 'BP 123 Cotonou',
            'country' => 'BJ',
            'status' => 'active',
        ]);

        Supplier::create([
            'tenant_id' => $tenant->id,
            'name' => 'Fournisseur Premium SARL',
            'email' => 'ventes@premium.bj',
            'phone' => '+229 95 12 34 56',
            'address' => 'Lot 45 Tokpa Dossou',
            'country' => 'BJ',
            'status' => 'active',
        ]);

        // Créer des produits de base pour restaurant
        $products = [
            [
                'name' => 'Riz Blanc 50kg',
                'code' => 'RIZ-50-BL',
                'category' => 'Céréales',
                'purchase_price' => 15000,
                'selling_price' => 18000,
                'unit' => 'sac',
                'min_stock' => 10,
                'tax_percent' => 0,
            ],
            [
                'name' => 'Farine de maïs 50kg',
                'code' => 'MAIS-50-FL',
                'category' => 'Céréales',
                'purchase_price' => 12000,
                'selling_price' => 14500,
                'unit' => 'sac',
                'min_stock' => 8,
                'tax_percent' => 0,
            ],
            [
                'name' => 'Huile de Palme 20L',
                'code' => 'HUILE-20-PL',
                'category' => 'Huiles',
                'purchase_price' => 8000,
                'selling_price' => 10000,
                'unit' => 'jerrican',
                'min_stock' => 15,
                'tax_percent' => 0,
            ],
            [
                'name' => 'Oignon rouge 50kg',
                'code' => 'OIGNON-50',
                'category' => 'Légumes',
                'purchase_price' => 20000,
                'selling_price' => 24000,
                'unit' => 'sac',
                'min_stock' => 5,
                'tax_percent' => 0,
            ],
            [
                'name' => 'Tomate Pelée 400g',
                'code' => 'TOMATE-400',
                'category' => 'Conserves',
                'purchase_price' => 1500,
                'selling_price' => 2000,
                'unit' => 'boîte',
                'min_stock' => 50,
                'tax_percent' => 0,
            ],
            [
                'name' => 'Piment fort 1kg',
                'code' => 'PIMENT-1',
                'category' => 'Épices',
                'purchase_price' => 3000,
                'selling_price' => 4000,
                'unit' => 'kg',
                'min_stock' => 3,
                'tax_percent' => 0,
            ],
            [
                'name' => 'Sel fin 50kg',
                'code' => 'SEL-50',
                'category' => 'Condiments',
                'purchase_price' => 5000,
                'selling_price' => 6500,
                'unit' => 'sac',
                'min_stock' => 10,
                'tax_percent' => 0,
            ],
            [
                'name' => 'Sauce Soja 500ml',
                'code' => 'SAUCE-500',
                'category' => 'Sauces',
                'purchase_price' => 2500,
                'selling_price' => 3500,
                'unit' => 'bouteille',
                'min_stock' => 30,
                'tax_percent' => 0,
            ],
        ];

        foreach ($products as $product_data) {
            Product::create([
                'tenant_id' => $tenant->id,
                'name' => $product_data['name'],
                'code' => $product_data['code'],
                'category' => $product_data['category'],
                'purchase_price' => $product_data['purchase_price'],
                'selling_price' => $product_data['selling_price'],
                'unit' => $product_data['unit'],
                'min_stock' => $product_data['min_stock'],
                'tax_percent' => $product_data['tax_percent'],
                'track_stock' => true,
                'status' => 'active',
            ]);
        }

        echo "✅ Demo data seeded for tenant: {$tenant->name}\n";
        echo "📧 Email: edmond@restaurantafrica.com\n";
        echo "🔑 Password: demo123456\n";
        echo "📦 Products: " . Product::where('tenant_id', $tenant->id)->count() . " created\n";
        echo "🏢 Warehouses: " . Warehouse::where('tenant_id', $tenant->id)->count() . " created\n";
        echo "👥 Users: " . User::where('tenant_id', $tenant->id)->count() . " created\n";
    }
}
