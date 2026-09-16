<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubmissionStatus;
use App\Models\Book;
use App\Models\BookSubmission;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookSubmission>
 */
class BookSubmissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_id' => null,
            'device_id' => Device::factory(),
            'status' => SubmissionStatus::Pending,
            'payload' => [
                'source_key' => 'user_submission',
                'url' => 'https://example.uz/api/v1/books/suggestions',
                'title' => 'Choʻlpon: Kecha va kunduz',
                'authors' => ['Abdulhamid Choʻlpon'],
                'publisher' => 'Akademnashr',
                'isbn' => '978-9943-6501-9-0',
                'published_year' => '2021',
                'pages' => '336',
            ],
            'cover_path' => null,
        ];
    }

    /**
     * A proposed change to a book already in the catalogue.
     */
    public function forBook(Book $book): static
    {
        return $this->state(fn (array $attributes): array => ['book_id' => $book->id]);
    }

    public function withCover(string $path = 'submissions/example.jpg'): static
    {
        return $this->state(fn (array $attributes): array => ['cover_path' => $path]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubmissionStatus::Approved,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(string $note = 'The photograph does not show a book.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubmissionStatus::Rejected,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);
    }
}
