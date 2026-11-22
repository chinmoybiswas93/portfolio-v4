<?php

declare(strict_types=1);

use App\Http\Controllers\Site\CvController;
use App\Http\Controllers\Site\FallbackController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\InvoiceLinkController;
use App\Http\Controllers\Site\PostController;
use App\Http\Controllers\Site\ProjectController;
use App\Http\Controllers\Site\SeoController;
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

Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/feed.xml', [SeoController::class, 'feed'])->name('feed');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

// Signed, expiring link to one invoice PDF version (sent in the invoice email). No login, no listing.
Route::get('/invoice/{pdf}', InvoiceLinkController::class)->middleware(['signed', 'throttle:30,1'])->whereNumber('pdf')->name('invoices.link');

Route::fallback(FallbackController::class);
