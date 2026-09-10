<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Scraping\Http\RobotsRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RobotsRulesTest extends TestCase
{
    private const UA = 'BookScraperBot/1.0 (+https://example.uz/bot)';

    public function test_an_empty_file_allows_everything(): void
    {
        $rules = RobotsRules::parse('', self::UA);

        $this->assertTrue($rules->allows('/'));
        $this->assertTrue($rules->allows('/kitob/123'));
    }

    public function test_it_honours_a_disallow(): void
    {
        $rules = RobotsRules::parse("User-agent: *\nDisallow: /admin", self::UA);

        $this->assertFalse($rules->allows('/admin'));
        $this->assertFalse($rules->allows('/admin/users'));
        $this->assertTrue($rules->allows('/kitob/123'));
    }

    public function test_disallow_slash_closes_the_whole_site(): void
    {
        $rules = RobotsRules::parse("User-agent: *\nDisallow: /", self::UA);

        $this->assertFalse($rules->allows('/'));
        $this->assertFalse($rules->allows('/anything'));
    }

    public function test_an_empty_disallow_means_allow_everything(): void
    {
        $rules = RobotsRules::parse("User-agent: *\nDisallow:", self::UA);

        $this->assertTrue($rules->allows('/anything'));
    }

    public function test_the_longest_matching_rule_wins(): void
    {
        $rules = RobotsRules::parse(
            "User-agent: *\nDisallow: /kitob\nAllow: /kitob/public",
            self::UA,
        );

        $this->assertFalse($rules->allows('/kitob/private'));
        $this->assertTrue($rules->allows('/kitob/public/1'));
    }

    public function test_allow_wins_a_tie(): void
    {
        $rules = RobotsRules::parse(
            "User-agent: *\nDisallow: /kitob\nAllow: /kitob",
            self::UA,
        );

        $this->assertTrue($rules->allows('/kitob/1'));
    }

    public function test_a_group_written_for_us_beats_the_catch_all(): void
    {
        $body = <<<'ROBOTS'
        User-agent: *
        Disallow: /

        User-agent: BookScraperBot
        Disallow: /admin
        ROBOTS;

        $rules = RobotsRules::parse($body, self::UA);

        $this->assertTrue($rules->allows('/kitob/1'));
        $this->assertFalse($rules->allows('/admin/x'));
    }

    public function test_we_fall_back_to_the_catch_all_group(): void
    {
        $body = <<<'ROBOTS'
        User-agent: Googlebot
        Disallow:

        User-agent: *
        Disallow: /private
        ROBOTS;

        $rules = RobotsRules::parse($body, self::UA);

        $this->assertFalse($rules->allows('/private/x'));
        $this->assertTrue($rules->allows('/kitob/1'));
    }

    public function test_consecutive_user_agent_lines_share_one_group(): void
    {
        $body = <<<'ROBOTS'
        User-agent: SomeBot
        User-agent: BookScraperBot
        Disallow: /nope
        ROBOTS;

        $rules = RobotsRules::parse($body, self::UA);

        $this->assertFalse($rules->allows('/nope'));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function wildcards(): array
    {
        return [
            'star matches a run' => ['/*.pdf', '/files/manual.pdf', false],
            'star does not match other paths' => ['/*.pdf', '/files/manual.html', true],
            'dollar anchors the end' => ['/search$', '/search', false],
            'dollar does not match longer' => ['/search$', '/search/results', true],
            'prefix without star' => ['/cart', '/cart/checkout', false],
        ];
    }

    #[DataProvider('wildcards')]
    public function test_it_understands_wildcards(string $pattern, string $path, bool $allowed): void
    {
        $rules = RobotsRules::parse("User-agent: *\nDisallow: {$pattern}", self::UA);

        $this->assertSame($allowed, $rules->allows($path));
    }

    public function test_it_ignores_comments_and_blank_lines(): void
    {
        $body = "# a comment\n\nUser-agent: *   # trailing\nDisallow: /admin\n\n";

        $this->assertFalse(RobotsRules::parse($body, self::UA)->allows('/admin'));
    }

    public function test_it_reads_a_crawl_delay(): void
    {
        $rules = RobotsRules::parse("User-agent: *\nCrawl-delay: 2.5\nDisallow: /admin", self::UA);

        $this->assertSame(2.5, $rules->crawlDelay());
        $this->assertFalse($rules->allows('/admin'));
    }

    public function test_no_crawl_delay_is_null(): void
    {
        $this->assertNull(RobotsRules::parse("User-agent: *\nDisallow:", self::UA)->crawlDelay());
    }

    public function test_deny_all_and_allow_all_shortcuts(): void
    {
        $this->assertFalse(RobotsRules::denyAll()->allows('/'));
        $this->assertFalse(RobotsRules::denyAll()->allows('/kitob/1'));
        $this->assertTrue(RobotsRules::allowAll()->allows('/anything'));
    }
}
