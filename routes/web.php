<?php

declare(strict_types=1);

use App\Http\Controllers\BookController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ScrapeRunController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::get('/books', [BookController::class, 'index'])->name('books.index');
Route::get('/books/{book}', [BookController::class, 'show'])->name('books.show');

Route::get('/scrape-runs', [ScrapeRunController::class, 'index'])->name('scrape-runs.index');
Route::post('/scrape-runs', [ScrapeRunController::class, 'store'])->name('scrape-runs.store');
Route::get('/scrape-runs/{scrapeRun}', [ScrapeRunController::class, 'show'])->name('scrape-runs.show');
