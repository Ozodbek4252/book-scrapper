<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\TrustLevel;
use App\Scraping\Drivers\AsaxiyUzDriver;
use App\Scraping\SourceRegistry;
use InvalidArgumentException;
use Tests\TestCase;

class SourceRegistryTest extends TestCase
{
    public function test_it_lists_the_configured_sources(): void
    {
        $this->assertContains('asaxiy_uz', $this->registry()->keys());
        $this->assertTrue($this->registry()->has('asaxiy_uz'));
        $this->assertFalse($this->registry()->has('not-a-shop.uz'));
        // kitob.uz was dropped: it publishes no ISBNs, so it cannot answer a scan.
        $this->assertFalse($this->registry()->has('kitob_uz'));
    }

    public function test_asaxiy_is_the_only_source_with_a_driver_so_far(): void
    {
        $registry = $this->registry();

        $this->assertTrue($registry->hasDriver('asaxiy_uz'));

        foreach (array_diff($registry->keys(), ['asaxiy_uz']) as $key) {
            $this->assertFalse($registry->hasDriver($key), "[{$key}] has a driver already.");
        }
    }

    public function test_only_the_source_with_a_driver_is_switched_on(): void
    {
        $registry = $this->registry();

        foreach ($registry->keys() as $key) {
            $this->assertSame(
                $registry->hasDriver($key),
                $registry->isEnabled($key),
                "[{$key}] should only be enabled once it has a driver.",
            );
        }
    }

    public function test_the_asaxiy_driver_resolves_to_the_real_class(): void
    {
        $this->assertInstanceOf(
            AsaxiyUzDriver::class,
            $this->registry()->driverFor('asaxiy_uz'),
        );
    }

    public function test_publisher_sites_outrank_bookstores(): void
    {
        $registry = $this->registry();

        $this->assertSame(TrustLevel::Bookstore, $registry->trustLevel('asaxiy_uz'));
        $this->assertSame(TrustLevel::Publisher, $registry->trustLevel('akademnashr'));
        $this->assertTrue($registry->trustLevel('akademnashr')->outranks($registry->trustLevel('asaxiy_uz')));
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
        $this->assertNull($this->registry()->driverFor('akademnashr'));
    }

    private function registry(): SourceRegistry
    {
        return app(SourceRegistry::class);
    }
}
