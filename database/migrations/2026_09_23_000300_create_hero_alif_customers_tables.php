<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 3 — Hero Alif customers + due ledger.
     *
     * The ledger is append-only: corrections happen as reversal entries,
     * never edits (spec §16). customers.due_balance is a cached balance
     * maintained by HaCustomerLedgerService inside transactions.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ha_customers')) {
            Schema::create('ha_customers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index()
                    ->comment('Linked registered website user (Phase 4), if any');
                $table->string('name', 160);
                $table->string('mobile', 32)->unique();
                $table->string('email', 160)->nullable();
                $table->text('address')->nullable();
                $table->string('customer_type', 16)->default('retail')->index()
                    ->comment('retail, wholesale');
                $table->decimal('due_balance', 12, 2)->default(0.00)
                    ->comment('Cached: latest ledger balance_after');
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                $table->softDeletes()->index();
            });
        }

        if (! Schema::hasTable('ha_customer_ledger')) {
            Schema::create('ha_customer_ledger', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->index();
                $table->string('type', 24)->index()
                    ->comment('opening_due, credit_sale, payment, adjustment, refund_reversal');
                $table->decimal('amount', 12, 2)->comment('Signed: + increases due, − reduces due');
                $table->decimal('balance_before', 12, 2);
                $table->decimal('balance_after', 12, 2);
                $table->string('reference_type', 64)->nullable()->index();
                $table->unsignedBigInteger('reference_id')->nullable()->index();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->timestamps();

                $table->index(['customer_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('ha_customer_payments')) {
            Schema::create('ha_customer_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id')->index();
                $table->unsignedBigInteger('sale_id')->nullable()->index()
                    ->comment('Set when collecting against a specific invoice');
                $table->decimal('amount', 12, 2);
                $table->string('method', 24)->default('cash')
                    ->comment('cash, bkash, nagad, bank, other_mbanking');
                $table->string('reference', 64)->nullable();
                $table->text('note')->nullable();
                $table->unsignedBigInteger('received_by')->nullable()->index();
                $table->timestamps();

                $table->index(['customer_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        foreach (['ha_customer_payments', 'ha_customer_ledger', 'ha_customers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
