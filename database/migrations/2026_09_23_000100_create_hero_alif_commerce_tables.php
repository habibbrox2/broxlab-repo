<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hero Alif commerce foundation (Phase 1):
     * categories, brands, products, inventory_movements,
     * suppliers, purchases, purchase_items.
     *
     * All tables carry the `ha_` prefix — the shared production DB already
     * has unrelated taxonomy tables (categories/tags) for content, and legacy
     * tables must never be touched. Money columns follow the wallet
     * convention: decimal(12,2). Foreign keys are intentionally omitted
     * (index-only) to stay safe on the shared MyISAM-era schema; integrity
     * is enforced in the service layer inside transactions.
     *
     * Idempotent: every create is guarded with hasTable/hasColumn, matching
     * the other migrations in this repo.
     */
    public function up(): void
    {
        // ── ha_categories ───────────────────────────────────────────────
        if (! Schema::hasTable('ha_categories')) {
            Schema::create('ha_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 140)->unique();
                $table->unsignedBigInteger('parent_id')->nullable()->index();
                $table->string('module', 32)->default('smart_bazar')->index()
                    ->comment('smart_bazar, mustard_oil, fuel, machinery, printing, other');
                $table->string('icon', 64)->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes()->index();
            });
        }

        // ── ha_brands ───────────────────────────────────────────────────
        if (! Schema::hasTable('ha_brands')) {
            Schema::create('ha_brands', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 140)->unique();
                $table->string('logo_path', 255)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->softDeletes()->index();
            });
        }

        // ── ha_products ────────────────────────────────────────────────
        if (! Schema::hasTable('ha_products')) {
            Schema::create('ha_products', function (Blueprint $table) {
                $table->id();
                $table->string('sku', 64)->unique();
                $table->string('barcode', 64)->nullable()->index();
                $table->string('name', 255);
                $table->string('slug', 255)->unique();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('category_id')->nullable()->index();
                $table->unsignedBigInteger('brand_id')->nullable()->index();
                $table->string('module', 32)->default('smart_bazar')->index()
                    ->comment('smart_bazar, mustard_oil, fuel, machinery, printing, other');
                $table->string('unit', 24)->default('pcs')
                    ->comment('pcs, liter, kg, box, meter, service');
                $table->boolean('is_physical')->default(true)
                    ->comment('false for services (no stock tracking)');
                $table->decimal('cost_price', 12, 2)->default(0.00);
                $table->decimal('retail_price', 12, 2)->default(0.00);
                $table->decimal('wholesale_price', 12, 2)->nullable();
                $table->integer('stock_qty')->default(0)->index();
                $table->integer('min_stock')->default(0);
                $table->integer('max_stock')->default(0)->comment('0 = unlimited');
                $table->integer('reorder_level')->default(0);
                $table->string('image_path', 255)->nullable();
                $table->json('attributes')->nullable()
                    ->comment('Module-specific attributes (volume, wattage, size...)');
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                $table->softDeletes()->index();

                $table->index(['module', 'is_active']);
                $table->index(['category_id', 'is_active']);
                $table->index('name');
            });
        }

        // ── ha_inventory_movements (ledger — never overwrite stock) ────
        if (! Schema::hasTable('ha_inventory_movements')) {
            Schema::create('ha_inventory_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('type', 32)->index()
                    ->comment('purchase, sale, sale_return, purchase_return, adjustment, damage, transfer_in, transfer_out, opening');
                $table->integer('qty')->comment('Signed: positive in, negative out');
                $table->integer('balance_after')->comment('Product stock after this movement');
                $table->decimal('unit_cost', 12, 2)->nullable()
                    ->comment('Cost captured at movement time for accurate P&L');
                $table->string('reference_type', 64)->nullable()->index()
                    ->comment('e.g. ha_purchases, ha_sales (future), manual');
                $table->unsignedBigInteger('reference_id')->nullable()->index();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();

                $table->index(['product_id', 'created_at']);
                $table->index(['reference_type', 'reference_id']);
            });
        }

        // ── ha_suppliers ───────────────────────────────────────────────
        if (! Schema::hasTable('ha_suppliers')) {
            Schema::create('ha_suppliers', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->string('shop_name', 160)->nullable();
                $table->string('mobile', 32)->nullable()->index();
                $table->string('email', 160)->nullable();
                $table->text('address')->nullable();
                $table->decimal('opening_due', 12, 2)->default(0.00)
                    ->comment('Positive = we owe supplier');
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                $table->softDeletes()->index();
            });
        }

        // ── ha_purchases ───────────────────────────────────────────────
        if (! Schema::hasTable('ha_purchases')) {
            Schema::create('ha_purchases', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_no', 40)->unique()->comment('PO/PINV-YYMMDD-XXXX');
                $table->unsignedBigInteger('supplier_id')->nullable()->index();
                $table->date('purchase_date')->index();
                $table->decimal('subtotal', 12, 2)->default(0.00);
                $table->decimal('discount', 12, 2)->default(0.00);
                $table->decimal('transport_cost', 12, 2)->default(0.00);
                $table->decimal('grand_total', 12, 2)->default(0.00);
                $table->decimal('paid_amount', 12, 2)->default(0.00);
                $table->decimal('due_amount', 12, 2)->default(0.00);
                $table->string('payment_method', 24)->default('cash')
                    ->comment('cash, bkash, nagad, bank, due, mixed');
                $table->string('status', 24)->default('received')->index()
                    ->comment('ordered, received, partial, cancelled');
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();
                $table->softDeletes()->index();

                $table->index(['supplier_id', 'purchase_date']);
            });
        }

        // ── ha_purchase_items ──────────────────────────────────────────
        if (! Schema::hasTable('ha_purchase_items')) {
            Schema::create('ha_purchase_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('purchase_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->integer('qty');
                $table->integer('received_qty')->default(0);
                $table->decimal('unit_cost', 12, 2);
                $table->decimal('line_total', 12, 2);
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['purchase_id', 'product_id']);
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'ha_purchase_items',
            'ha_purchases',
            'ha_suppliers',
            'ha_inventory_movements',
            'ha_products',
            'ha_brands',
            'ha_categories',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
