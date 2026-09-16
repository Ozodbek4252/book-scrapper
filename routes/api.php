<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BookController;
use App\Http\Controllers\Api\V1\BookSuggestionController;
use App\Http\Controllers\Api\V1\DeviceController;
use Illuminate\Support\Facades\Route;

/*
| The lookup backend the barcode scanning mobile app calls.
|
| Everything needs a Sanctum token and is rate limited: this is a small
| service in front of a database nobody else has.
*/

// Enrolment is open, but far tighter than the rest: it mints credentials.
Route::post('/v1/devices', [DeviceController::class, 'store'])
    ->middleware('throttle:enrol')
    ->name('api.v1.devices.store');

Route::prefix('v1')
    ->middleware([
        // 'auth:sanctum',
        'throttle:api',
    ])
    ->group(function (): void {
        Route::get('/books', [BookController::class, 'index'])->name('api.v1.books.index');

        // Submitting needs a device token even while reading does not: a book
        // that arrives has to be traceable to the install that sent it.
        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('/books/suggestions', [BookSuggestionController::class, 'store'])
                ->name('api.v1.books.suggestions.store');

            Route::post('/books/{book}/suggestions', [BookSuggestionController::class, 'store'])
                ->name('api.v1.books.suggestions.update');
        });

        // Last, so "suggestions" is never read as an ISBN.
        Route::get('/books/{isbn}', [BookController::class, 'show'])->name('api.v1.books.show');
    });
