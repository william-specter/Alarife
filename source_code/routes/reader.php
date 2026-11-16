<?php

use App\Http\Controllers\Reader\ReaderController;
use App\Http\Controllers\Reader\ThemeStylesheetController;
use Illuminate\Support\Facades\Route;

Route::get('/themes/{theme}.css', ThemeStylesheetController::class)->name('themes.css');

Route::get('/{encyclopedia:slug}', [ReaderController::class, 'home'])->name('reader.home');
Route::get('/{encyclopedia:slug}/topics/{topicSlug}', [ReaderController::class, 'topic'])->name('reader.topic');
Route::get('/{encyclopedia:slug}/articles/{articleSlug}', [ReaderController::class, 'article'])->name('reader.article');
Route::get('/{encyclopedia:slug}/search', [ReaderController::class, 'search'])->middleware('throttle:60,1')->name('reader.search');
Route::get('/{encyclopedia:slug}/download', [ReaderController::class, 'download'])->middleware('throttle:10,1')->name('reader.download');
