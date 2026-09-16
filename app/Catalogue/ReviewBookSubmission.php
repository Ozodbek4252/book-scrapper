<?php

declare(strict_types=1);

namespace App\Catalogue;

use App\Enums\SubmissionStatus;
use App\Enums\TrustLevel;
use App\Models\Book;
use App\Models\BookSubmission;
use App\Models\User;
use App\Scraping\DTO\RawBook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * What happens when a human decides about a submitted book.
 *
 * Approving folds the submission into the catalogue through the same merge
 * path a scrape uses. Rejecting changes nothing: whatever the catalogue
 * already said about that book still stands.
 */
final readonly class ReviewBookSubmission
{
    public function __construct(private UpsertBookFromSource $upsert) {}

    /**
     * Accept a submission, optionally with the reviewer's own corrections.
     *
     * Fields the reviewer changed are recorded in `locked_fields`, so a later
     * scrape cannot undo a human's decision. Fields they merely waved through
     * stay open, so a better source can still improve them.
     *
     * @param  array<string, mixed>  $corrections  Reviewer edits, in RawBook shape.
     */
    public function approve(BookSubmission $submission, User $reviewer, array $corrections = []): Book
    {
        return DB::transaction(function () use ($submission, $reviewer, $corrections): Book {
            $payload = array_merge($submission->payload, $corrections);
            $raw = RawBook::fromArray($payload);

            $edited = $this->editedFields($submission->payload, $corrections);
            $coverPath = $this->publishCover($submission);

            if ($coverPath !== null) {
                // array_merge, not +: the payload already carries a cover_path
                // key (usually null), and + would keep that null.
                $payload = array_merge($payload, ['cover_path' => $coverPath]);
                $raw = RawBook::fromArray($payload);
                $edited[] = 'cover_path';
            }

            $book = $submission->isUpdate()
                ? $this->applyToExisting($submission->book, $raw)
                : $this->upsert->handle($raw, TrustLevel::UserSubmission)['book'];

            $book->forceFill([
                'verified' => true,
                'locked_fields' => array_values(array_unique(array_merge($book->locked_fields ?? [], $edited))),
            ])->save();

            $submission->update([
                'status' => SubmissionStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'payload' => $payload,
                'cover_path' => $coverPath ?? $submission->cover_path,
                'book_id' => $book->id,
            ]);

            return $book->refresh();
        });
    }

    /**
     * Turn a submission down. The catalogue is left exactly as it was.
     */
    public function reject(BookSubmission $submission, User $reviewer, ?string $note = null): BookSubmission
    {
        // The photograph is dropped: it was never published, and keeping
        // rejected uploads means storing whatever people pointed a camera at.
        if ($submission->cover_path !== null) {
            Storage::disk('local')->delete($submission->cover_path);
        }

        $submission->update([
            'status' => SubmissionStatus::Rejected,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
            'cover_path' => null,
        ]);

        return $submission->refresh();
    }

    /**
     * An accepted change to a book we already hold.
     *
     * The book is known, so the merge must not be allowed to pick a different
     * one from the ISBN or fingerprint in the payload.
     */
    private function applyToExisting(Book $book, RawBook $raw): Book
    {
        $this->upsert->applyTo($book, $raw, TrustLevel::UserSubmission);

        return $book->refresh();
    }

    /**
     * Which fields the reviewer actually changed.
     *
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $corrections
     * @return array<int, string>
     */
    private function editedFields(array $original, array $corrections): array
    {
        $changed = [];

        foreach ($corrections as $field => $value) {
            if (($original[$field] ?? null) !== $value) {
                $changed[] = self::columnFor($field);
            }
        }

        return array_values(array_filter($changed));
    }

    /**
     * Move an approved photograph onto the public disk.
     */
    private function publishCover(BookSubmission $submission): ?string
    {
        $path = $submission->cover_path;

        if ($path === null || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $published = 'covers/'.basename($path);

        Storage::disk('public')->put($published, Storage::disk('local')->get($path));
        Storage::disk('local')->delete($path);

        return $published;
    }

    /**
     * RawBook field names to the columns the merge writes.
     */
    private static function columnFor(string $field): string
    {
        return match ($field) {
            'isbn' => 'isbn13',
            'published_year' => 'published_year',
            'authors' => 'authors',
            default => $field,
        };
    }
}
