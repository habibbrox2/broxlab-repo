<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7: operating expenses (rent, utilities, transport, salary...).
 * Money columns decimal(12,2) per repo convention; no FKs (legacy shared schema).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ha_expenses')) {
            return;
        }

        Schema::create('ha_expenses', function (Blueprint $table) {
            $table->increments('id');
            $table->string('category', 60)->comment('rent|utility|salary|transport|purchase|marketing|other');
            $table->decimal('amount', 12, 2);
            $table->string('note', 255)->nullable();
            $table->date('expense_date')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index(['expense_date', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ha_expenses');
    }
};
