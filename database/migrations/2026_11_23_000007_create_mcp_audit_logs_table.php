<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only record of every MCP tool call (ADR-0009 §6). Stores the token id, never the token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('client_id', 64)->nullable()->index();
            $table->string('client_name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token_id', 100)->nullable();
            $table->string('method', 64);
            $table->string('tool', 100)->nullable()->index();
            $table->string('scope', 64)->nullable();
            $table->string('target_type', 32)->nullable();
            $table->string('target_id', 64)->nullable();
            $table->string('target_label')->nullable();
            $table->json('changes')->nullable();
            $table->string('result', 16)->index();
            $table->unsignedSmallInteger('http_status');
            $table->string('error', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_audit_logs');
    }
};
