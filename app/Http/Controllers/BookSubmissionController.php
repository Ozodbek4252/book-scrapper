<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Catalogue\ReviewBookSubmission;
use App\Enums\SubmissionStatus;
use App\Models\BookSubmission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookSubmissionController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->enum('status', SubmissionStatus::class) ?? SubmissionStatus::Pending;

        return view('submissions.index', [
            'submissions' => BookSubmission::query()
                ->with(['book:id,title', 'device:id,uuid,platform'])
                ->where('status', $status)
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
            'status' => $status,
            'counts' => BookSubmission::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    public function show(BookSubmission $submission): View
    {
        $submission->load(['book.authors', 'book.publisher', 'device', 'reviewer']);

        return view('submissions.show', [
            'submission' => $submission,
            'proposed' => $submission->toRawBook(),
        ]);
    }

    /**
     * Serve an unreviewed photograph to the reviewer.
     *
     * It lives on the private disk precisely so it is not publicly reachable,
     * so the review screen has to stream it rather than link to it.
     */
    public function cover(BookSubmission $submission): StreamedResponse
    {
        abort_if($submission->cover_path === null, 404);
        abort_unless(Storage::disk('local')->exists($submission->cover_path), 404);

        return Storage::disk('local')->response($submission->cover_path);
    }

    /**
     * Accept a submission, with whatever the reviewer corrected.
     */
    public function approve(Request $request, BookSubmission $submission, ReviewBookSubmission $review): RedirectResponse
    {
        if (! $submission->status->isOpen()) {
            return back()->withErrors(['submission' => 'That submission has already been decided.']);
        }

        $corrections = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'authors' => ['nullable', 'string', 'max:1000'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:32'],
            'published_year' => ['nullable', 'integer', 'min:1400', 'max:'.(date('Y') + 1)],
            'pages' => ['nullable', 'integer', 'min:1', 'max:20000'],
            'language' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        // The form edits authors as one line; the payload holds a list.
        $corrections['authors'] = array_values(array_filter(
            array_map('trim', explode(',', (string) ($corrections['authors'] ?? ''))),
            static fn (string $name): bool => $name !== '',
        ));

        $corrections = array_map(
            static fn (mixed $value): mixed => is_int($value) ? (string) $value : $value,
            $corrections,
        );

        $book = $review->approve($submission, $this->reviewer($request), $corrections);

        return redirect()
            ->route('submissions.index')
            ->with('status', "Approved. “{$book->title}” is in the catalogue.");
    }

    public function reject(Request $request, BookSubmission $submission, ReviewBookSubmission $review): RedirectResponse
    {
        if (! $submission->status->isOpen()) {
            return back()->withErrors(['submission' => 'That submission has already been decided.']);
        }

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $review->reject($submission, $this->reviewer($request), $validated['review_note'] ?? null);

        return redirect()
            ->route('submissions.index')
            ->with('status', 'Rejected. The catalogue is unchanged.');
    }

    /**
     * Who is reviewing.
     *
     * The admin screens have no sign-in yet, so this falls back to the first
     * user. Wire real authentication in before this is reachable publicly.
     */
    private function reviewer(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : User::firstOrFail();
    }
}
