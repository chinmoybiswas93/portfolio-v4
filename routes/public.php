<?php

declare(strict_types=1);

use App\Http\Controllers\Site\CvController;
use App\Http\Controllers\Site\FallbackController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\PostController;
use App\Http\Controllers\Site\ProjectController;
use Illuminate\Support\Facades\Route;

/*
 * Public site: "public" middleware group (no session, no cookies, cached pages). See ADR-0007/0008.
 */
Route::get('/', HomeController::class)->name('home');

Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
Route::get('/projects/{project:slug}', [ProjectController::class, 'show'])->name('projects.show');

Route::get('/blog', [PostController::class, 'index'])->name('posts.index');
Route::get('/blog/category/{category:slug}', [PostController::class, 'category'])->name('posts.category');
Route::get('/blog/tag/{tag:slug}', [PostController::class, 'tag'])->name('posts.tag');
Route::get('/blog/{post:slug}', [PostController::class, 'show'])->name('posts.show');

Route::get('/cv', CvController::class)->name('cv');

Route::fallback(FallbackController::class);
