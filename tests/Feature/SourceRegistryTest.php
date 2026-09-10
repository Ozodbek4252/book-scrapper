<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TrustLevel;
use App\Scraping\SourceRegistry;
use InvalidArgumentException;
use Tests\TestCase;

class SourceRegistryTest extends TestCase
{
    public function test_it_lists_the_configured_sources(): void
    {
        $this->assertContains('kitob_uz', $this->registry()->keys());
        $this->assertTrue($this->registry()->has('kitob_uz'));
        $this->assertFalse($this->registry()->has('not-a-shop.uz'));
    }

    public function test_no_source_ships_with_a_driver_or_switched_on(): void
    {
        foreach ($this->registry()->keys() as $key) {
            $this->assertFalse($this->registry()->hasDriver($key), "[{$key}] has a driver already.");
            $this->assertFalse($this->registry()->isEnabled($key), "[{$key}] is enabled already.");
        }
    }

    public function test_publisher_sites_outrank_bookstores(): void
    {
        $registry = $this->registry();

        $this->assertSame(TrustLevel::Bookstore, $registry->trustLevel('kitob_uz'));
        $this->assertSame(TrustLevel::Publisher, $registry->trustLevel('akademnashr'));
        $this->assertTrue($registry->trustLevel('akademnashr')->outranks($registry->trustLevel('kitob_uz')));
    }

    public function test_an_unknown_source_is_trusted_least(): void
    {
        $this->assertSame(TrustLevel::UserSubmission, $this->registry()->trustLevel('who-is-this'));
    }

    public function test_it_rejects_a_driver_that_does_not_implement_the_contract(): void
    {
        config(['scraping.sources.broken' => ['driver' => \stdClass::class, 'enabled' => true]]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement');

        $this->registry()->driverFor('broken');
    }

    public function test_it_returns_null_when_a_source_has_no_driver(): void
    {
        $this->assertNull($this->registry()->driverFor('kitob_uz'));
    }

    private function registry(): SourceRegistry
    {
        return app(SourceRegistry::class);
    }
}
