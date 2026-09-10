<?php

declare(strict_types=1);

namespace App\Scraping\Http;

/**
 * The rules one robots.txt states for one crawler.
 *
 * Longest matching pattern wins, and Allow beats Disallow on a tie, which is
 * how the major crawlers read the file (RFC 9309).
 */
final readonly class RobotsRules
{
    /**
     * @param  array<int, array{allow: bool, pattern: string}>  $rules
     */
    public function __construct(
        private array $rules = [],
        private ?float $crawlDelay = null,
    ) {}

    public static function allowAll(): self
    {
        return new self;
    }

    public static function denyAll(): self
    {
        return new self([['allow' => false, 'pattern' => '/']]);
    }

    /**
     * Read a robots.txt for one crawler.
     *
     * Groups are matched by substring, so "BookScraperBot/1.0 (+…)" picks up a
     * group written for "bookscraperbot". A group for our own name wins over
     * the catch-all "*".
     */
    public static function parse(string $body, string $userAgent): self
    {
        /** @var array<string, array<int, array{allow: bool, pattern: string}>> $groups */
        $groups = [];
        /** @var array<string, float> $delays */
        $delays = [];
        $currentAgents = [];
        $lastLineWasAgent = false;

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');

            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = mb_strtolower($field);

            if ($field === 'user-agent') {
                // Consecutive User-agent lines share one group of rules.
                if (! $lastLineWasAgent) {
                    $currentAgents = [];
                }

                $currentAgents[] = mb_strtolower($value);
                $lastLineWasAgent = true;

                continue;
            }

            $lastLineWasAgent = false;

            if ($currentAgents === []) {
                continue;
            }

            foreach ($currentAgents as $agent) {
                if ($field === 'crawl-delay' && is_numeric($value)) {
                    $delays[$agent] = (float) $value;

                    continue;
                }

                if ($field !== 'allow' && $field !== 'disallow') {
                    continue;
                }

                // "Disallow:" with nothing after it means allow everything.
                if ($field === 'disallow' && $value === '') {
                    continue;
                }

                $groups[$agent][] = ['allow' => $field === 'allow', 'pattern' => $value];
            }
        }

        $key = self::groupFor(array_keys($groups + $delays), $userAgent);

        return new self($groups[$key] ?? [], $delays[$key] ?? null);
    }

    /**
     * Pick the group written for us, else the catch-all.
     *
     * @param  array<int, string>  $agents
     */
    private static function groupFor(array $agents, string $userAgent): string
    {
        $userAgent = mb_strtolower($userAgent);

        foreach ($agents as $agent) {
            if ($agent !== '*' && $agent !== '' && str_contains($userAgent, $agent)) {
                return $agent;
            }
        }

        return '*';
    }

    /**
     * Whether we may fetch this path.
     */
    public function allows(string $path): bool
    {
        if ($path === '') {
            $path = '/';
        }

        $winner = null;

        foreach ($this->rules as $rule) {
            if (! $this->matches($rule['pattern'], $path)) {
                continue;
            }

            $length = mb_strlen($rule['pattern']);

            // Longest pattern wins; Allow wins a tie.
            if ($winner === null
                || $length > $winner['length']
                || ($length === $winner['length'] && $rule['allow'])) {
                $winner = ['allow' => $rule['allow'], 'length' => $length];
            }
        }

        return $winner === null || $winner['allow'];
    }

    public function crawlDelay(): ?float
    {
        return $this->crawlDelay;
    }

    /**
     * robots.txt patterns support * for any run of characters and $ for
     * end-of-path. Everything else is a literal prefix.
     */
    private function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');

        if ($anchored) {
            $pattern = mb_substr($pattern, 0, -1);
        }

        $regex = implode('.*', array_map(
            static fn (string $part): string => preg_quote($part, '#'),
            explode('*', $pattern),
        ));

        return preg_match('#^'.$regex.($anchored ? '$' : '').'#u', $path) === 1;
    }
}
