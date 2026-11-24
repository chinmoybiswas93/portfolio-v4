<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Key takeaways, FAQ and a "last reviewed" date on posts (ADR-0010, slice 8c). All nullable, additive. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->json('takeaways')->nullable()->after('body_html');
            $table->json('faq')->nullable()->after('takeaways');
            $table->date('reviewed_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn(['takeaways', 'faq', 'reviewed_at']);
        });
    }
};
