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
            // Legacy users.id is signed int(11) — an InnoDB FK would fail with
            // errno 150, so reference it by indexed column only (same convention
            // as the Hero Alif migrations).
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['scope', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_api_keys');
    }
};
