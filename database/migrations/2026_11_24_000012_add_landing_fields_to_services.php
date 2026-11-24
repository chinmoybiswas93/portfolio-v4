<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Service landing pages (ADR-0010, slice 8d): FAQ, contact line and SEO fields. All nullable, additive. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->json('faq')->nullable()->after('body_html');
            $table->string('cta_line', 160)->nullable()->after('faq');
            $table->string('seo_title', 60)->nullable()->after('cta_line');
            $table->string('seo_description', 160)->nullable()->after('seo_title');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['faq', 'cta_line', 'seo_title', 'seo_description']);
        });
    }
};
