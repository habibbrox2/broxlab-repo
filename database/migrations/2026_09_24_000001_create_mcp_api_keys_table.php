<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key_hash', 255)->comment('bcrypt hash of the API key');
            $table->string('name', 100)->default('API Key')
                ->comment('Human-readable label for the key');
            $table->enum('scope', ['read', 'write'])->default('read')
                ->comment('Read-only or read+write access');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['scope', 'revoked_at']);
            $table->foreign('created_by')->references('id')->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_api_keys');
    }
};
