<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookSuggestionRequest;
use App\Models\Book;
use App\Models\BookSubmission;
use App\Models\Device;
use App\Scraping\DTO\RawBook;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class BookSuggestionController extends Controller
{
    /**
     * Take a book an app user says is missing, or a change to one we hold.
     *
     * Nothing reaches the catalogue here. The submission waits in
     * `book_submissions` until a human approves it, because people photograph
     * the wrong thing and mistype titles. `$book` being null means the user is
     * proposing a book we do not have; otherwise it is a proposed edit.
     */
    public function store(StoreBookSuggestionRequest $request, ?Book $book = null): JsonResponse
    {
        $validated = $request->validated();
        $device = $request->user();

        if ($device instanceof Device && $device->isBlocked()) {
            return response()->json(['message' => 'This device cannot submit books.'], 403);
        }

        // The private disk: an unreviewed photograph must never be publicly
        // reachable. It is published only once the submission is approved.
        $coverPath = $request->hasFile('cover')
            ? $request->file('cover')->store('submissions', 'local')
            : null;

        $raw = new RawBook(
            sourceKey: 'user_submission',
            url: $request->url(),
            externalId: 'device:'.(string) $device?->getKey().':'.md5((string) json_encode(Arr::except($validated, ['cover']))),
            title: $validated['title'],
            subtitle: $validated['subtitle'] ?? null,
            authors: array_values($validated['authors'] ?? []),
            publisher: $validated['publisher'] ?? null,
            isbn: $validated['isbn'] ?? null,
            publishedYear: isset($validated['published_year']) ? (string) $validated['published_year'] : null,
            pages: isset($validated['pages']) ? (string) $validated['pages'] : null,
            language: $validated['language'] ?? null,
            description: $validated['description'] ?? null,
        );

        $submission = BookSubmission::create([
            'status' => SubmissionStatus::Pending,
            'book_id' => $book?->id,
            'device_id' => $device instanceof Device ? $device->getKey() : null,
            'payload' => $raw->toArray(),
            'cover_path' => $coverPath,
        ]);

        return response()->json([
            'message' => $submission->isUpdate()
                ? 'Thank you. Your change is waiting for review.'
                : 'Thank you. The book is waiting for review.',
            'submission' => [
                'id' => $submission->id,
                'status' => $submission->status->value,
            ],
        ], 202);
    }
}
