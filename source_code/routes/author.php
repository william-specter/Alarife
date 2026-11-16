<?php

use App\Http\Controllers\Author\ArticleController;
use App\Http\Controllers\Author\DashboardController;
use App\Http\Controllers\Author\EncyclopediaController;
use App\Http\Controllers\Author\ExportController;
use App\Http\Controllers\Author\MediaController;
use App\Http\Controllers\Author\ReferenceController;
use App\Http\Controllers\Author\TopicController;
use App\Http\Controllers\Author\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/encyclopedias/create', [EncyclopediaController::class, 'create'])->name('encyclopedias.create');
    Route::post('/encyclopedias', [EncyclopediaController::class, 'store'])->name('encyclopedias.store');

    Route::get('/author/references/search', [ReferenceController::class, 'search'])
        ->middleware('throttle:60,1')
        ->name('author.references.search');

    Route::prefix('/author/{encyclopedia:slug}')->name('author.')->group(function () {
        Route::get('/overview', [WorkspaceController::class, 'overview'])->name('overview');

        Route::get('/settings', [EncyclopediaController::class, 'settings'])->name('settings');
        Route::put('/settings', [EncyclopediaController::class, 'update'])->name('settings.update');
        Route::delete('/encyclopedia', [EncyclopediaController::class, 'destroy'])->name('encyclopedias.destroy');
        Route::post('/duplicate', [EncyclopediaController::class, 'duplicate'])->name('encyclopedias.duplicate');

        Route::get('/covers', [WorkspaceController::class, 'covers'])->name('covers.index');
        Route::post('/covers', [WorkspaceController::class, 'storeCover'])->name('covers.store');
        Route::put('/covers/reorder', [WorkspaceController::class, 'reorderCovers'])->name('covers.reorder');
        Route::put('/covers/{cover}', [WorkspaceController::class, 'updateCover'])->name('covers.update');
        Route::delete('/covers/{cover}', [WorkspaceController::class, 'destroyCover'])->name('covers.destroy');

        Route::get('/topics', [TopicController::class, 'index'])->name('topics.index');
        Route::post('/topics', [TopicController::class, 'store'])->name('topics.store');
        Route::put('/topics/reorder', [TopicController::class, 'reorder'])->name('topics.reorder');
        Route::put('/topics/{topic}', [TopicController::class, 'update'])->name('topics.update');
        Route::delete('/topics/{topic}', [TopicController::class, 'destroy'])->name('topics.destroy');

        Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
        Route::post('/articles', [ArticleController::class, 'store'])->name('articles.store');
        Route::get('/articles/{article}/edit', [ArticleController::class, 'edit'])->name('articles.edit');
        Route::put('/articles/{article}', [ArticleController::class, 'update'])->name('articles.update');
        Route::patch('/articles/{article}/autosave', [ArticleController::class, 'autosave'])->name('articles.autosave');
        Route::get('/articles/{article}/revisions', [ArticleController::class, 'revisions'])->name('articles.revisions');
        Route::post('/articles/{article}/status', [ArticleController::class, 'status'])->name('articles.status');
        Route::delete('/articles/{article}', [ArticleController::class, 'destroy'])->name('articles.destroy');
        Route::post('/articles/{article}/duplicate', [ArticleController::class, 'duplicate'])->name('articles.duplicate');
        Route::post('/articles/{article}/revisions/{revision}/restore', [ArticleController::class, 'restore'])->name('articles.restore');

        Route::get('/media', [MediaController::class, 'index'])->name('media.index');
        Route::post('/media', [MediaController::class, 'store'])->middleware('throttle:30,1')->name('media.store');
        Route::put('/media/{media}', [MediaController::class, 'update'])->name('media.update');
        Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

        Route::get('/appearance', [WorkspaceController::class, 'appearance'])->name('appearance.index');
        Route::put('/appearance', [WorkspaceController::class, 'updateAppearance'])->name('appearance.update');
        Route::get('/preview', [WorkspaceController::class, 'preview'])->name('preview');
        Route::post('/preview/frame', [WorkspaceController::class, 'previewFrame'])->name('preview.frame');

        Route::get('/publishing', [WorkspaceController::class, 'publishing'])->name('publishing.index');
        Route::post('/publishing', [WorkspaceController::class, 'publish'])->name('publishing.store');
        Route::post('/publishing/unpublish', [WorkspaceController::class, 'unpublish'])->name('publishing.unpublish');

        Route::get('/exports', [ExportController::class, 'index'])->name('exports.index');
        Route::post('/exports', [ExportController::class, 'store'])->middleware('throttle:10,1')->name('exports.store');
        Route::get('/exports/{export}/status', [ExportController::class, 'status'])->name('exports.status');
        Route::get('/exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');
    });
});
