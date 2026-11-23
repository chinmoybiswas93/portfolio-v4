<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One shared tag pool for posts, projects and experience (owner, 2026-11-22). Tags with the same slug are merged
 * into the oldest one, their links are moved across, and the topic/tech type column is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $groups = DB::table('tags')->orderBy('id')->get(['id', 'slug'])->groupBy('slug');
            foreach ($groups as $tags) {
                $keep = (int) $tags->first()->id;
                foreach ($tags->slice(1) as $duplicate) {
                    $links = DB::table('taggables')->where('tag_id', $duplicate->id)->get(['taggable_id', 'taggable_type']);
                    foreach ($links as $link) {
                        DB::table('taggables')->insertOrIgnore([
                            'tag_id' => $keep,
                            'taggable_id' => $link->taggable_id,
                            'taggable_type' => $link->taggable_type,
                        ]);
                    }
                    DB::table('taggables')->where('tag_id', $duplicate->id)->delete();
                    DB::table('tags')->where('id', $duplicate->id)->delete();
                }
            }
        });

        Schema::table('tags', function (Blueprint $table): void {
            $table->dropUnique(['slug', 'type']);
        });
        Schema::table('tags', function (Blueprint $table): void {
            $table->dropColumn('type');
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->string('type', 16)->default('topic');
        });
        Schema::table('tags', function (Blueprint $table): void {
            $table->unique(['slug', 'type']);
        });
    }
};
