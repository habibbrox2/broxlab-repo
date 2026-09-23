<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 4 — Hero Alif online orders.
     *
     * Orders come from the storefront cart; stock is validated and prices
     * resolved server-side at order placement (spec §11). Confirmed orders
     * flow into the same sales engine (ha_sales, sale_type=online) so all
     * financial reporting stays in one place.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ha_orders')) {
            Schema::create('ha_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_no', 40)->unique()->comment('ORD-YYMMDD-XXXX');
                $table->string('tracking_id', 24)->unique()->comment('Customer-facing tracking token');
                $table->unsignedBigInteger('customer_id')->nullable()->index()->comment('ha_customers');
                $table->unsignedBigInteger('user_id')->nullable()->index()->comment('website user (legacy users table)');
                $table->string('customer_name', 160);
                $table->string('customer_mobile', 32)->index();
                $table->text('customer_address');
                $table->decimal('subtotal', 12, 2)->default(0.00);
                $table->decimal('discount', 12, 2)->default(0.00);
                $table->decimal('shipping_fee', 12, 2)->default(0.00);
                $table->decimal('grand_total', 12, 2)->default(0.00);
                $table->string('payment_method', 24)->default('cash_on_delivery')
                    ->comment('cash_on_delivery, bkash, nagad, bank');
                $table->string('payment_status', 16)->default('unpaid')->index()
                    ->comment('unpaid, advance_paid, paid');
                $table->string('status', 20)->default('pending')->index()
                    ->comment('pending, confirmed, processing, ready, shipped, delivered, cancelled, returned');
                $table->unsignedBigInteger('sale_id')->nullable()->index()->comment('ha_sales row once confirmed');
                $table->text('note')->nullable();
                $table->timestamp('placed_at')->nullable();
                $table->timestamps();
                $table->softDeletes()->index();

                $table->index(['status', 'created_at']);
            });
        }

        if (! Schema::hasTable('ha_order_items')) {
            Schema::create('ha_order_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('name', 255)->comment('Snapshot');
                $table->string('sku', 64);
                $table->integer('qty');
                $table->decimal('unit_price', 12, 2)->comment('Server-side price snapshot');
                $table->decimal('line_total', 12, 2);
                $table->timestamps();

                $table->index(['order_id', 'product_id']);
            });
        }

        if (! Schema::hasTable('ha_order_status_history')) {
            Schema::create('ha_order_status_history', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->index();
                $table->string('from_status', 20)->nullable();
                $table->string('to_status', 20);
                $table->text('note')->nullable();
                $table->unsignedBigInteger('changed_by')->nullable()->index();
                $table->timestamps();

                $table->index(['order_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        foreach (['ha_order_status_history', 'ha_order_items', 'ha_orders'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
