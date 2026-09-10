<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\Publisher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookApiTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function book(): Book
    {
        $book = Book::factory()
            ->for(Publisher::factory()->create(['name' => 'Академнашр', 'name_latin' => 'Akademnashr']))
            ->create([
                'isbn13' => '9789943650190',
                'title' => 'Choʻlpon: Kecha va kunduz',
                'title_latin' => 'Choʻlpon: Kecha va kunduz',
                'title_cyrillic' => 'Чўлпон: Кеча ва кундуз',
                'published_year' => 2021,
                'pages' => 336,
            ]);

        $book->authors()->attach(
            Author::factory()->create(['full_name' => 'Abdulhamid Choʻlpon']),
            ['position' => 0],
        );

        return $book;
    }

    public function test_the_api_is_closed_without_a_token(): void
    {
        Book::factory()->create();

        $this->getJson('/api/v1/books')->assertUnauthorized();
        $this->getJson('/api/v1/books/9789943650190')->assertUnauthorized();
        $this->postJson('/api/v1/books/suggestions', ['title' => 'Kitob'])->assertUnauthorized();
    }

    /**
     * A scanner, a website and a person all write an ISBN differently.
     *
     * @return array<string, array{0: string}>
     */
    public static function isbnShapes(): array
    {
        return [
            'plain isbn13' => ['9789943650190'],
            'hyphenated' => ['978-9943-6501-9-0'],
            'spaced' => ['978 9943 6501 9 0'],
            'with a label' => ['ISBN 978-9943-6501-9-0'],
        ];
    }

    #[DataProvider('isbnShapes')]
    public function test_any_shape_of_isbn_finds_the_book(string $isbn): void
    {
        $this->signIn();
        $this->book();

        $this->getJson('/api/v1/books/'.rawurlencode($isbn))
            ->assertOk()
            ->assertJsonPath('data.isbn13', '9789943650190');
    }

    public function test_an_isbn10_finds_the_book_stored_under_its_isbn13(): void
    {
        $this->signIn();
        Book::factory()->create(['isbn13' => '9780306406157']);

        $this->getJson('/api/v1/books/0306406152')
            ->assertOk()
            ->assertJsonPath('data.isbn13', '9780306406157');
    }

    public function test_it_returns_both_scripts_and_the_related_records(): void
    {
        $this->signIn();
        $this->book();

        $this->getJson('/api/v1/books/9789943650190')
            ->assertOk()
            ->assertJsonPath('data.title_latin', 'Choʻlpon: Kecha va kunduz')
            ->assertJsonPath('data.title_cyrillic', 'Чўлпон: Кеча ва кундуз')
            ->assertJsonPath('data.authors.0.full_name', 'Abdulhamid Choʻlpon')
            ->assertJsonPath('data.publisher.name', 'Академнашр')
            ->assertJsonPath('data.published_year', 2021)
            ->assertJsonPath('data.pages', 336);
    }

    public function test_a_mis_scanned_isbn_is_rejected_rather_than_reported_missing(): void
    {
        $this->signIn();

        // Same digits, wrong check digit.
        $this->getJson('/api/v1/books/9789943650191')
            ->assertStatus(422)
            ->assertJsonPath('message', 'That is not a valid ISBN-10 or ISBN-13.');
    }

    public function test_a_valid_but_unknown_isbn_is_a_404(): void
    {
        $this->signIn();

        $this->getJson('/api/v1/books/9780306406157')
            ->assertNotFound()
            ->assertJsonPath('isbn13', '9780306406157');
    }

    public function test_the_listing_returns_a_paginated_envelope(): void
    {
        $this->signIn();
        Book::factory()->count(3)->create();

        $this->getJson('/api/v1/books')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'isbn13', 'title_latin', 'title_cyrillic']], 'meta' => ['total']]);
    }

    public function test_the_page_size_is_capped(): void
    {
        $this->signIn();
        Book::factory()->count(3)->create();

        $this->getJson('/api/v1/books?per_page=5000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_suggestions_is_not_shadowed_by_the_isbn_route(): void
    {
        $this->signIn();

        // "suggestions" must not be read as an ISBN.
        $this->postJson('/api/v1/books/suggestions', ['title' => 'Yangi kitob'])
            ->assertStatus(202);
    }
}
