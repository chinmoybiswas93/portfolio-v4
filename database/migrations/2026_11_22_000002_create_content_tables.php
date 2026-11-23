<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 500)->nullable();
            $table->text('body_md')->nullable();
            $table->text('body_html')->nullable();
            $table->string('icon', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('experiences', function (Blueprint $table): void {
            $table->id();
            $table->string('company');
            $table->string('company_url', 512)->nullable();
            $table->string('position');
            $table->string('location')->nullable();
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->text('description_md')->nullable();
            $table->text('description_html')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->unique(['company', 'started_on']);
            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 500)->nullable();
            $table->string('client_name')->nullable();
            $table->string('sector')->nullable();
            $table->string('role')->nullable();
            $table->string('made_at')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->text('problem_md')->nullable();
            $table->text('problem_html')->nullable();
            $table->text('approach_md')->nullable();
            $table->text('approach_html')->nullable();
            $table->text('results_md')->nullable();
            $table->text('results_html')->nullable();
            $table->json('metrics')->nullable();
            $table->string('live_url', 512)->nullable();
            $table->string('repo_url', 512)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('has_case_study')->default(false);
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'sort_order']);
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('body_md')->nullable();
            $table->longText('body_html')->nullable();
            $table->unsignedSmallInteger('reading_minutes')->default(1);
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at']);
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('type', 16)->default('topic');
            $table->timestamps();
            $table->unique(['slug', 'type']);
        });

        Schema::create('taggables', function (Blueprint $table): void {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');
            $table->primary(['tag_id', 'taggable_id', 'taggable_type']);
        });

        Schema::create('redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('from_path', 512)->unique();
            $table->string('to_path', 512);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('experiences');
        Schema::dropIfExists('services');
    }
};
