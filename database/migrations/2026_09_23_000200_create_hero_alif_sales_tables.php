<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 2 — Hero Alif sales, payments, cash registers.
     * Conventions mirror the Phase 1 migration: ha_ prefix, decimal(12,2)
     * money, idempotent guards, indexes without FK constraints.
     *
     * Financial rows are immutable by policy: refunds create compensating
     * rows and flip ha_sales.status — history is never rewritten.
     */
    public function up(): void
    {
        // ── ha_sales ───────────────────────────────────────────────────
        if (! Schema::hasTable('ha_sales')) {
            Schema::create('ha_sales', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_no', 40)->unique()->comment('INV-YYMMDD-XXXX, prefix configurable');
                $table->unsignedBigInteger('customer_id')->nullable()->index()
                    ->comment('ha_customers (Phase 3); null = walk-in');
                $table->unsignedBigInteger('cashier_id')->nullable()->index();
                $table->string('sale_type', 16)->default('pos')->index()->comment('pos, online');
                $table->string('status', 16)->default('completed')->index()
                    ->comment('held, completed, refunded, cancelled');
                $table->decimal('subtotal', 12, 2)->default(0.00);
                $table->decimal('discount', 12, 2)->default(0.00);
                $table->decimal('vat_percent', 5, 2)->default(0.00);
                $table->decimal('vat_amount', 12, 2)->default(0.00);
                $table->decimal('grand_total', 12, 2)->default(0.00);
                $table->decimal('paid_amount', 12, 2)->default(0.00)->comment('Excludes due; capped at grand total');
                $table->decimal('due_amount', 12, 2)->default(0.00);
                $table->decimal('change_amount', 12, 2)->default(0.00);
                $table->unsignedBigInteger('register_id')->nullable()->index();
                $table->text('note')->nullable();
                $table->timestamp('held_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->string('refund_reason', 255)->nullable();
                $table->timestamps();
                $table->softDeletes()->index();

                $table->index(['status', 'created_at']);
                $table->index(['cashier_id', 'created_at']);
            });
        }

        // ── ha_sale_items ──────────────────────────────────────────────
        if (! Schema::hasTable('ha_sale_items')) {
            Schema::create('ha_sale_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sale_id')->index();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('name', 255)->comment('Snapshot at sale time');
                $table->string('sku', 64);
                $table->string('module', 32)->index()->comment('Snapshot for module-wise revenue');
                $table->integer('qty');
                $table->decimal('unit_price', 12, 2)->comment('Server-side price snapshot');
                $table->decimal('unit_cost', 12, 2)->nullable()->comment('Cost snapshot for real P&L');
                $table->decimal('line_total', 12, 2);
                $table->timestamps();

                $table->index(['sale_id', 'product_id']);
                $table->index(['module', 'created_at']);
            });
        }

        // ── ha_sale_payments ───────────────────────────────────────────
        if (! Schema::hasTable('ha_sale_payments')) {
            Schema::create('ha_sale_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sale_id')->index();
                $table->string('kind', 16)->default('payment')->comment('payment, refund');
                $table->string('method', 24)->index()
                    ->comment('cash, bkash, nagad, bank, other_mbanking, due');
                $table->decimal('amount', 12, 2);
                $table->string('reference', 64)->nullable()->comment('bKash/Nagad trx id etc.');
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();

                $table->index(['sale_id', 'kind']);
                $table->index(['method', 'created_at']);
            });
        }

        // ── ha_cash_registers ──────────────────────────────────────────
        if (! Schema::hasTable('ha_cash_registers')) {
            Schema::create('ha_cash_registers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('opened_by')->nullable()->index();
                $table->timestamp('opened_at');
                $table->decimal('opening_balance', 12, 2)->default(0.00);
                $table->unsignedBigInteger('closed_by')->nullable()->index();
                $table->timestamp('closed_at')->nullable();
                $table->decimal('expected_cash', 12, 2)->nullable();
                $table->decimal('actual_cash', 12, 2)->nullable();
                $table->decimal('difference', 12, 2)->nullable();
                $table->string('status', 16)->default('open')->index()->comment('open, closed');
                $table->text('note')->nullable();
                $table->timestamps();
            });
        }

        // ── ha_cash_register_transactions ─────────────────────────────
        if (! Schema::hasTable('ha_cash_register_transactions')) {
            Schema::create('ha_cash_register_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('register_id')->index();
                $table->string('type', 32)->index()
                    ->comment('cash_sale, change, cash_in, cash_out, refund_cash, due_collection');
                $table->decimal('amount', 12, 2)->comment('Signed: positive cash in, negative out');
                $table->string('reference_type', 64)->nullable()->index();
                $table->unsignedBigInteger('reference_id')->nullable()->index();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();

                $table->index(['register_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'ha_cash_register_transactions',
            'ha_cash_registers',
            'ha_sale_payments',
            'ha_sale_items',
            'ha_sales',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
