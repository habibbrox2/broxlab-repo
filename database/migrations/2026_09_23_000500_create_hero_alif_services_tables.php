<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ha_service_categories')) {
            Schema::create('ha_service_categories', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('icon')->nullable();
                $table->text('description')->nullable();
                $table->decimal('base_fee', 12, 2)->default(0);
                $table->json('form_fields')->nullable();
                $table->unsignedTinyInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index('is_active');
            });
        }

        if (!Schema::hasTable('ha_service_requests')) {
            Schema::create('ha_service_requests', function (Blueprint $table) {
                $table->increments('id');
                $table->string('tracking_id', 24)->unique(); // DS-2026-00125
                $table->unsignedInteger('category_id');
                $table->unsignedBigInteger('user_id')->nullable(); // null = guest request
                $table->unsignedBigInteger('ha_customer_id')->nullable();
                $table->string('name', 120);
                $table->string('mobile', 20);
                $table->string('email', 150)->nullable();
                $table->string('address', 255)->nullable();
                $table->text('description')->nullable();
                $table->json('form_data')->nullable();
                $table->string('contact_method', 20)->default('phone');
                $table->unsignedTinyInteger('priority')->default(2); // 1=low 2=normal 3=high
                $table->string('status', 30)->default('pending');
                $table->decimal('fee', 12, 2)->default(0);
                $table->string('payment_status', 20)->default('unpaid');
                $table->unsignedInteger('assigned_staff_id')->nullable();
                $table->text('staff_notes')->nullable();      // internal only
                $table->text('customer_notes')->nullable();   // visible to requester
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'created_at']);
                $table->index('mobile');
                $table->index('category_id');
            });
        }

        if (!Schema::hasTable('ha_service_documents')) {
            Schema::create('ha_service_documents', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('service_request_id');
                $table->string('storage_path');  // random path on private disk
                $table->string('original_name', 255);
                $table->string('mime', 100);
                $table->unsignedInteger('size_bytes');
                $table->timestamps();
                $table->index('service_request_id');
            });
        }

        if (!Schema::hasTable('ha_service_status_history')) {
            Schema::create('ha_service_status_history', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('service_request_id');
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30);
                $table->text('note')->nullable();
                $table->unsignedInteger('actor_id')->nullable(); // admin/staff user
                $table->timestamps();
                $table->index(['service_request_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ha_service_status_history');
        Schema::dropIfExists('ha_service_documents');
        Schema::dropIfExists('ha_service_requests');
        Schema::dropIfExists('ha_service_categories');
    }
};
