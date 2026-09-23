<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the user wallet system: a balance column on users plus a
     * wallet-transaction ledger and a recharges table for top-ups.
     *
     * Money columns are decimal(12,2) — supports up to 999,999,999.99 BDT
     * which is plenty for per-service fees on this platform.
     */
    public function up(): void
    {
        // ── users.balance ──────────────────────────────────────────────
        // Guard with a column-exists check so the migration is idempotent on
        // the shared MySQL DB where migrations aren't strictly locked.
        if (! Schema::hasColumn('users', 'balance')) {
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('balance', 12, 2)
                    ->default(0.00)
                    ->comment('Wallet balance used to pay for paid services');
            });
        }

        // ── user_wallet_transactions (ledger) ────────────────────────
        if (! Schema::hasTable('user_wallet_transactions')) {
            Schema::create('user_wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('type', 32)->index()
                    ->comment('recharge, service_payment, refund, adjustment, cancellation_fee');
                $table->decimal('amount', 12, 2)->comment('Absolute value of the movement');
                $table->decimal('balance_before', 12, 2);
                $table->decimal('balance_after', 12, 2);
                $table->string('currency', 8)->default('BDT');
                $table->string('reference_type')->nullable()->index()
                    ->comment('Polymorphic reference, e.g. UserRecharge, service_applications');
                $table->unsignedBigInteger('reference_id')->nullable()->index();
                $table->string('description')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
            });
        }

        // ── user_recharges (top-ups) ───────────────────────────────────
        if (! Schema::hasTable('user_recharges')) {
            Schema::create('user_recharges', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->decimal('amount', 12, 2);
                $table->string('currency', 8)->default('BDT');
                $table->string('method', 32)->default('bkash')
                    ->comment('bkash, nagad, card, handcash, bank');
                $table->string('transaction_id', 128)->nullable()->index();
                $table->string('payer_phone', 32)->nullable();
                $table->string('status', 32)->default('pending')
                    ->index()
                    ->comment('pending, processing, completed, failed, cancelled, expired');
                $table->string('admin_note', 512)->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index(['transaction_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_wallet_transactions');
        Schema::dropIfExists('user_recharges');

        if (Schema::hasColumn('users', 'balance')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('balance');
            });
        }
    }
};
