<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BookController;
use App\Http\Controllers\Api\V1\BookSuggestionController;
use Illuminate\Support\Facades\Route;

/*
| The lookup backend the barcode scanning mobile app calls.
|
| Everything needs a Sanctum token and is rate limited: this is a small
| service in front of a database nobody else has.
*/

Route::prefix('v1')
    ->middleware([
        // 'auth:sanctum',
        'throttle:api'
    ])
    ->group(function (): void {
        Route::get('/books', [BookController::class, 'index'])->name('api.v1.books.index');
        Route::post('/books/suggestions', [BookSuggestionController::class, 'store'])->name('api.v1.books.suggestions.store');

        // Last, so "suggestions" is never read as an ISBN.
        Route::get('/books/{isbn}', [BookController::class, 'show'])->name('api.v1.books.show');
    });
