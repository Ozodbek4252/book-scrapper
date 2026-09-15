<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookSuggestionRequest;
use App\Jobs\NormalizeAndUpsertJob;
use App\Scraping\DTO\RawBook;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class BookSuggestionController extends Controller
{
    /**
     * Take a book a mobile app user could not find.
     *
     * It goes through exactly the same merge path a scraped book does, at the
     * lowest trust level, so any real source outranks it field by field. It
     * arrives unverified and waits for a human.
     */
    public function store(StoreBookSuggestionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Stored before the job is queued: the uploaded file only lives for
        // the length of this request.
        $coverPath = $request->hasFile('cover')
            ? $request->file('cover')->store('covers', 'public')
            : null;

        // The book is identified by what it says, not by which photograph came
        // with it — the same book sent twice with two different snapshots is
        // still one submission.
        $identity = Arr::except($validated, ['cover']);

        $raw = new RawBook(
            sourceKey: 'user_submission',
            url: $request->url(),
            externalId: 'user:'.(string) $request->user()?->id.':'.md5((string) json_encode($identity)),
            title: $validated['title'],
            subtitle: $validated['subtitle'] ?? null,
            authors: array_values($validated['authors'] ?? []),
            publisher: $validated['publisher'] ?? null,
            isbn: $validated['isbn'] ?? null,
            publishedYear: isset($validated['published_year']) ? (string) $validated['published_year'] : null,
            pages: isset($validated['pages']) ? (string) $validated['pages'] : null,
            language: $validated['language'] ?? null,
            description: $validated['description'] ?? null,
            coverPath: $coverPath,
            payload: ['submitted_by' => $request->user()?->id],
        );

        NormalizeAndUpsertJob::dispatch('user_submission', $raw->toArray());

        return response()->json([
            'message' => 'Thank you. The book has been queued for review.',
        ], 202);
    }
}
