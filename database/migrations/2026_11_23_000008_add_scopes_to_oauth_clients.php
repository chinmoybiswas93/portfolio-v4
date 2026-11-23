<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The scopes the owner approved for each MCP app on the consent screen (ADR-0009 §2). Passport's Client model
 * reads this column; null means "not approved yet".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->json('scopes')->nullable()->after('grant_types');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_clients', function (Blueprint $table): void {
            $table->dropColumn('scopes');
        });
    }
};
