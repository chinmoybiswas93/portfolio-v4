<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trash for services, tags and categories (posts, projects and clients already have it). MCP can only move to
 * the trash and restore; permanent delete stays in the admin (ADR-0009 §4).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['services', 'tags', 'categories'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->softDeletes());
        }
    }

    public function down(): void
    {
        foreach (['services', 'tags', 'categories'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropSoftDeletes());
        }
    }
};
