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

    public function test_only_the_sources_that_were_worth_one_have_a_driver(): void
    {
        $registry = $this->registry();
        $written = ['asaxiy_uz', 'olcha_uz', 'qamar_uz', 'hilolnashr_uz'];

        foreach ($written as $key) {
            $this->assertTrue($registry->hasDriver($key), "[{$key}] should have a driver.");
        }

        // The publisher sites publish no ISBNs, or no catalogue at all.
        foreach (array_diff($registry->keys(), $written) as $key) {
            $this->assertFalse($registry->hasDriver($key), "[{$key}] has a driver already.");
        }
    }

    public function test_a_source_without_a_driver_is_never_switched_on(): void
    {
        $registry = $this->registry();

        foreach ($registry->keys() as $key) {
            if (! $registry->hasDriver($key)) {
                $this->assertFalse($registry->isEnabled($key), "[{$key}] is enabled but cannot run.");
            }
        }

        // Enabling a source is a deliberate act, so a new driver stays off.
        $this->assertFalse($registry->isEnabled('olcha_uz'));
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
        config(['scraping.sources.a_publisher' => ['driver' => null, 'enabled' => false, 'trust_level' => TrustLevel::Publisher]]);
        $registry = $this->registry();

        $this->assertSame(TrustLevel::Bookstore, $registry->trustLevel('asaxiy_uz'));
        $this->assertSame(TrustLevel::Publisher, $registry->trustLevel('a_publisher'));
        $this->assertTrue($registry->trustLevel('a_publisher')->outranks($registry->trustLevel('asaxiy_uz')));
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
        $this->assertNull($this->registry()->driverFor('user_submission'));
    }

    public function test_runnable_keys_excludes_no_driver_and_disabled_sources(): void
    {
        config(['scraping.sources' => [
            'ready' => ['driver' => AsaxiyUzDriver::class, 'enabled' => true],
            'written_but_off' => ['driver' => AsaxiyUzDriver::class, 'enabled' => false],
            'no_driver_yet' => ['driver' => null, 'enabled' => false],
        ]]);

        $this->assertSame(['ready'], $this->registry()->runnableKeys());
    }

    private function registry(): SourceRegistry
    {
        return app(SourceRegistry::class);
    }
}
