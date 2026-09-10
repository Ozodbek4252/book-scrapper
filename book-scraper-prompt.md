# Prompt: Uzbek book metadata scraper (Laravel)

Copy everything below the line into your coding agent, inside the fresh Laravel project.

---

## Context

This is a fresh Laravel app. Its single job is to build a **canonical database of books published in Uzbekistan**, by scraping Uzbek online bookstores and publisher websites, and to expose that database as a read-only API.

Why it exists: Google Books and Open Library have almost no coverage of books with Uzbek ISBN prefixes (`978-9943`, `978-9910`), and there is no public API from the national ISBN agency. A separate mobile app scans book barcodes in stores; this service is the lookup backend it calls.

**First targets:**

- Bookstores: `asaxiy.uz`, `kitob.uz`
- Publishers: `O'zbekiston NMIU`, `Yangi asr avlodi`, `Akademnashr` (add more later)

The architecture must make adding a new source a one-file job.

## Hard rules

1. **Do not invent HTML structure.** You cannot know the DOM of these sites. Build the framework and the normalization layer first. When you are ready to write a specific site parser, **stop and ask me to paste a real saved HTML page (or JSON response) from that site.** Never write CSS selectors from a guess.
2. **Check for a JSON endpoint before parsing HTML.** Most of these shops are JS front-ends over an internal REST API. Ask me to check the Network tab. A JSON endpoint is always preferred over DOM scraping — more stable and cheaper.
3. Laravel conventions, PSR-12, `declare(strict_types=1)` everywhere.
4. **Prefer plain functions for the transformation layer** (ISBN normalization, text cleaning, transliteration) — put them in a dedicated helpers file and unit test them. Use classes only where polymorphism or framework contracts require it: drivers, jobs, models, DTOs, form requests.
5. No business logic in controllers.
6. Zero hardcoded selectors scattered in code. Every site's selectors live in one place inside that site's driver, as class constants, so a broken parser is a one-line fix.

## Politeness and legal guardrails — build these in from the start

- Parse and honour `robots.txt` per domain. Config kill-switch to disable a source instantly.
- Default throttle: **1 request per second per domain**, configurable. Never parallelise against one domain.
- Descriptive User-Agent with a contact URL.
- Never scrape anything behind a login.
- **Cache every raw response to disk**, keyed by URL hash, before parsing. Re-parsing must never re-hit the network. This is what makes parser iteration fast and keeps you off their servers.
- Always store `source_url` on the record for attribution.

## Schema

Design migrations for:

- **`books`** — canonical, merged record. Fields: `isbn13` (nullable, unique), `isbn10`, `title`, `title_latin`, `title_cyrillic`, `title_normalized`, `subtitle`, `publisher_id`, `published_year`, `pages`, `language`, `description`, `cover_url`, `cover_path`, `fingerprint` (unique), `verified` (bool), `locked_fields` (json), timestamps.
- **`book_sources`** — one row per site that knows about this book: `book_id`, `source_key`, `external_id`, `url`, `raw_payload` (json — always keep it), `price`, `in_stock`, `scraped_at`. Unique on (`source_key`, `external_id`).
- **`authors`**, **`publishers`**, and an `author_book` pivot. Authors store `family_name` and `given_name` separately when detectable, plus a normalized full name for matching.
- **`scrape_runs`** — `source_key`, `status`, `started_at`, `finished_at`, `pages_scraped`, `items_found`, `items_new`, `items_updated`, `errors_count`.
- **`scrape_errors`** — `run_id`, `url`, `stage` (fetch/parse/upsert), `message`, `context` (json).

## Driver architecture

- Contract `App\Scraping\Contracts\SourceDriver`:
  - `key(): string`
  - `discover(): iterable` — yields product URLs (walk category pages / sitemap.xml — check for `sitemap.xml` first, it is often the cheapest full catalog listing)
  - `fetch(string $url): ?RawBook`
- `RawBook` — a readonly DTO of **raw strings exactly as found**, no cleaning. Cleaning happens in the normalization layer, so it can be re-run over stored `raw_payload` without re-scraping.
- Registry in `config/scraping.php`: `key => [driver, enabled, base_url, rate_limit, trust_level]`.
- `trust_level` matters for merging: publisher sites outrank bookstores, bookstores outrank user submissions.

## Pipeline (queued)

`DiscoverSourceJob` → `FetchBookPageJob` (one per URL) → `NormalizeAndUpsertJob`

- `FetchBookPageJob` implements `ShouldBeUnique` keyed on URL hash, uses `WithoutOverlapping` / a rate limiter per domain, has `backoff()`, and writes failures to `scrape_errors` in `failed()`.
- Schedule a nightly incremental run per enabled driver; a full re-crawl weekly.
- Supervisor-managed queue workers, separate `scraping` queue so it never blocks the API queue.

## Merge and dedupe rules

- Valid `isbn13` is the primary merge key.
- No ISBN → `fingerprint = sha1(normalized_title + '|' + normalized_first_author + '|' + year)`.
- On conflict, higher `trust_level` wins per field.
- Never overwrite a field listed in `locked_fields` (manually corrected data).
- Keep the raw payload from every source forever.

## Uzbek text normalization — this is the part that decides if the project works

Write and unit test pure functions for:

- **ISBN**: strip separators, validate check digit, convert ISBN-10 → ISBN-13, reject invalid codes early. Keep the raw scanned/scraped string separately.
- **Apostrophes**: `ʻ ʼ ' ' ' \` ´` all collapse to one canonical character. This alone breaks most naive matching.
- **Digraphs**: `o'` / `oʻ` / `ў`, `g'` / `gʻ` / `ғ`, plus `sh`, `ch`, `ng`.
- **Transliteration both ways**, Latin ↔ Cyrillic. Store `title_latin` and `title_cyrillic` on every book and index both. The same book is sold as "Oʻtkan kunlar" on one site and "Ўткан кунлар" on another — without this they become two records.
- **Title noise stripping**: `(qattiq muqova)`, `(yumshoq muqova)`, `2-nashr`, `to'ldirilgan nashr`, series names in brackets.
- **Author names**: handle both "Abdulla Qodiriy" and "Qodiriy Abdulla" ordering, initials, and Cyrillic spellings of the same person.

Cover these with a real test table of tricky Uzbek titles and author names. Ask me for examples if you need them.

## API

Laravel Sanctum token auth, rate limited, consistent JSON envelope.

- `GET /api/v1/books/{isbn}` — normalizes any ISBN-10/13 input format, returns canonical book with authors and publisher, or 404.
- `GET /api/v1/books?q=` — typo-tolerant search via Laravel Scout + Meilisearch. **Index both scripts** for title and author. Return `title_latin` and `title_cyrillic` in the payload.
- `POST /api/v1/books/suggestions` — accepts a book submitted by a mobile-app user (unknown ISBN). Stored unverified, lowest trust level, queued for review. Validate hard, this endpoint is public-facing.

## Admin / observability

Simple admin UI (Filament if you want it, plain Blade is fine):

- Scrape runs list with status and counts, drill into errors.
- Queue of unverified / user-submitted books for review and approval.
- Duplicate-merge screen (two candidate records side by side, pick the winner per field).
- Dashboard counters: total books, % with ISBN, % with cover, count per source, books added in last 7 days.

## Testing — non-negotiable

- Save a real HTML/JSON fixture per site in `tests/Fixtures/{source_key}/`. Parser tests run **fully offline** against fixtures. Site markup will change; these tests are what tell you which parser broke and when.
- `Http::fake()` for the fetch layer.
- Full unit coverage on ISBN and transliteration functions, including malformed input.

## Build order

Do these in sequence, and stop for my review after each step.

1. Migrations, models, config, `SourceDriver` contract, `RawBook` DTO. No scraping yet.
2. Normalization function library + its full test suite. Prove it works before anything touches the network.
3. Fetch layer: throttled HTTP client, robots.txt handling, raw response caching to disk.
4. **One driver end to end** (`kitob.uz` or whichever I give you HTML for first) → discover → fetch → normalize → upsert → visible in DB. Ask me for the sample page before you start.
5. The API endpoints + Meilisearch indexing.
6. Remaining drivers, one at a time, each with fixtures and tests.
7. Admin UI and metrics.

Ask me questions when a requirement is ambiguous instead of guessing. Prefer boring, obvious code over clever code.
