<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_logs', function (Blueprint $table) {
            $table->id();
            $table->string('key_name', 100)->comment('API key name at time of call');
            $table->string('client_hash', 16)->comment('First 16 chars of key SHA-256');
            $table->string('method', 64)->comment('JSON-RPC method or tool name');
            $table->string('status', 20)->default('success')->comment('success, invalid_argument, error, rate_limited, unauthorized');
            $table->unsignedSmallInteger('duration_ms')->default(0);
            $table->unsignedInteger('result_count')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['created_at', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_logs');
    }
};
