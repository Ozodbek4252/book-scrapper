<?php

declare(strict_types=1);

namespace App\Scraping;

use App\Enums\TrustLevel;
use App\Scraping\Contracts\SourceDriver;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Reads config/scraping.php and hands back drivers.
 *
 * Adding a source is meant to be a one-file job, so every question about
 * whether a source exists, is switched on, or can actually run is answered
 * here rather than scattered through jobs and controllers.
 */
final readonly class SourceRegistry
{
    public function __construct(private Container $container) {}

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->sources());
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->sources());
    }

    /**
     * Sources a run can actually be started for right now: a driver is
     * written and its own switch is on. Everything else — no driver yet,
     * or a written driver switched off — would only fail once dispatched.
     *
     * @return array<int, string>
     */
    public function runnableKeys(): array
    {
        return array_values(array_filter(
            $this->keys(),
            fn (string $key): bool => $this->hasDriver($key) && $this->isEnabled($key),
        ));
    }

    /**
     * Scraping can be switched off globally without a deploy.
     */
    public function scrapingEnabled(): bool
    {
        return (bool) config('scraping.enabled', true);
    }

    public function isEnabled(string $key): bool
    {
        return (bool) ($this->configFor($key)['enabled'] ?? false);
    }

    public function hasDriver(string $key): bool
    {
        return ($this->configFor($key)['driver'] ?? null) !== null;
    }

    public function trustLevel(string $key): TrustLevel
    {
        $level = $this->configFor($key)['trust_level'] ?? null;

        return $level instanceof TrustLevel
            ? $level
            : TrustLevel::tryFrom((int) $level) ?? TrustLevel::UserSubmission;
    }

    /**
     * Resolve the driver for a source, or null when none is written yet.
     */
    public function driverFor(string $key): ?SourceDriver
    {
        $class = $this->configFor($key)['driver'] ?? null;

        if ($class === null) {
            return null;
        }

        $driver = $this->container->make($class);

        if (! $driver instanceof SourceDriver) {
            throw new InvalidArgumentException(
                "Driver [{$class}] for source [{$key}] must implement ".SourceDriver::class.'.',
            );
        }

        return $driver;
    }

    /**
     * @return array<string, mixed>
     */
    public function configFor(string $key): array
    {
        return $this->sources()[$key] ?? [];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function sources(): array
    {
        return config('scraping.sources', []);
    }
}
