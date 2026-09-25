<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sponsored_packages', 'deleted_at')) {
            return;
        }
        Schema::table('sponsored_packages', function (Blueprint $table) {
            $table->softDeletes()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('sponsored_packages', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
