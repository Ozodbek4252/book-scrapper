<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_anonymous_caller_is_still_rate_limited_by_address(): void
    {
        config(['scraping.api_rate_limit' => 3]);
        Book::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->getJson('/api/v1/books')->assertOk();
        }

        $this->getJson('/api/v1/books')->assertStatus(429);
    }

    public function test_a_different_address_gets_its_own_allowance(): void
    {
        config(['scraping.api_rate_limit' => 2]);

        foreach (['1.1.1.1', '1.1.1.1'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->getJson('/api/v1/books')->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '1.1.1.1'])->getJson('/api/v1/books')->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '2.2.2.2'])->getJson('/api/v1/books')->assertOk();
    }
}
