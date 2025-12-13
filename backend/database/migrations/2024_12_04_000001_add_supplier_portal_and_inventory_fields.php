<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajouter les champs pour le portail fournisseur
        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('tenant_id')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('suppliers', 'has_portal_access')) {
                $table->boolean('has_portal_access')->default(false)->after('status');
            }
            if (!Schema::hasColumn('suppliers', 'portal_email')) {
                $table->string('portal_email')->nullable()->after('has_portal_access');
            }
            if (!Schema::hasColumn('suppliers', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('portal_email');
            }
        });

        // Ajouter les champs d'inventaire avancé aux produits
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'cmm')) {
                $table->decimal('cmm', 12, 2)->default(0)->after('max_stock')->comment('Consommation Moyenne Mensuelle');
            }
            if (!Schema::hasColumn('products', 'reorder_point')) {
                $table->integer('reorder_point')->default(0)->after('cmm')->comment('Point de commande');
            }
            if (!Schema::hasColumn('products', 'lead_time_days')) {
                $table->integer('lead_time_days')->default(7)->after('reorder_point')->comment('Délai de livraison en jours');
            }
        });

        // Ajouter les champs d'inventaire avancé aux stocks
        Schema::table('stocks', function (Blueprint $table) {
            if (!Schema::hasColumn('stocks', 'sdu_theorique')) {
                $table->integer('sdu_theorique')->default(0)->after('quantity')->comment('Stock Disponible Utilisable Théorique');
            }
            if (!Schema::hasColumn('stocks', 'stock_physique')) {
                $table->integer('stock_physique')->nullable()->after('sdu_theorique')->comment('Stock Physique après inventaire');
            }
            if (!Schema::hasColumn('stocks', 'ecart')) {
                $table->integer('ecart')->nullable()->after('stock_physique')->comment('Écart entre théorique et physique');
            }
            if (!Schema::hasColumn('stocks', 'last_inventory_at')) {
                $table->timestamp('last_inventory_at')->nullable()->after('ecart');
            }
        });

        // Table pour les commandes fournisseur (si n'existe pas)
        if (!Schema::hasTable('supplier_orders')) {
            Schema::create('supplier_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
                $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->string('reference')->unique();
                $table->enum('status', ['draft', 'sent', 'confirmed', 'shipped', 'received', 'cancelled'])->default('draft');
                $table->decimal('total', 15, 2)->default(0);
                $table->date('expected_delivery_date')->nullable();
                $table->date('actual_delivery_date')->nullable();
                $table->text('notes')->nullable();
                $table->text('supplier_notes')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamp('shipped_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamps();
                
                $table->index(['tenant_id', 'status']);
                $table->index(['supplier_id', 'status']);
            });
        }

        // Table pour les articles de commande fournisseur
        if (!Schema::hasTable('supplier_order_items')) {
            Schema::create('supplier_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->integer('quantity_ordered');
                $table->integer('quantity_received')->default(0);
                $table->decimal('unit_price', 12, 2);
                $table->decimal('total', 15, 2);
                $table->timestamps();
            });
        }

        // Table pour les demandes de stock (détail vers gros)
        if (!Schema::hasTable('stock_requests')) {
            Schema::create('stock_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('from_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->foreignId('to_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reference')->unique();
                $table->enum('status', ['pending', 'approved', 'rejected', 'transferred', 'cancelled'])->default('pending');
                $table->text('notes')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('transferred_at')->nullable();
                $table->timestamps();
                
                $table->index(['tenant_id', 'status']);
            });
        }

        // Table pour les articles de demande de stock
        if (!Schema::hasTable('stock_request_items')) {
            Schema::create('stock_request_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_request_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->integer('quantity_requested');
                $table->integer('quantity_approved')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_request_items');
        Schema::dropIfExists('stock_requests');
        Schema::dropIfExists('supplier_order_items');
        Schema::dropIfExists('supplier_orders');

        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn(['sdu_theorique', 'stock_physique', 'ecart', 'last_inventory_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['cmm', 'reorder_point', 'lead_time_days']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'has_portal_access', 'portal_email', 'last_login_at']);
        });
    }
};
