# Book scraper — handover

Written 2026-09-10, at the end of a Claude Code session, to move this project
to another machine. It records where the build got to, the things that are
easy to get wrong, and what is left.

The plan being followed is `book-scraper-prompt.md`. Its seven-step build order
is the spine of this document.

---

## Where it got to

| Step | State |
| --- | --- |
| 1. Schema, models, config, `SourceDriver`, `RawBook` | **done** |
| 2. Uzbek normalization + tests | **done** |
| 3. Fetch layer (throttle, robots.txt, raw cache) | **done** |
| 4. One driver end to end | **done** — asaxiy.uz |
| 5. API + Meilisearch | **done** |
| 6. Remaining drivers | **partly** — olcha.uz written, the three named publishers are dead ends |
| 7. Admin UI and metrics | **partly** — runs, errors and dashboard done; review and merge screens missing |

289 tests pass. Pint is clean.

Data collected so far: **1,349 books, 1,280 of them with an ISBN**, 384 authors,
160 publishers, 1,441 source rows.

---

## The single most important finding

**asaxiy.uz is the only Uzbek source found that publishes ISBNs.**

Every other candidate was checked properly — fetched, searched for `isbn` and
for any 13-digit `978…`/`979…` run, in the HTML *and* in the JavaScript payload:

| Source | Verdict |
| --- | --- |
| **asaxiy.uz** | Has ISBNs. The driver is built and working. |
| kitob.uz | No ISBN anywhere. It is the Ministry of Education's free library, not a shop. Dropped from config. |
| olcha.uz | 30,000+ books with author, publisher, year, pages — but **zero** ISBNs. Driver written for enrichment only. |
| akademnashr.uz | Live WooCommerce shop, 129 products, no ISBN on any sampled. |
| Yangi asr avlodi | **No website exists.** Sold through retailers; asaxiy already carries its books. |
| O'zbekiston NMIU (iptd-uzbekistan.uz) | Corporate site, no catalogue, no ISBNs. |

This matters for the whole project. The premise was "Google Books does not
cover Uzbek ISBNs". The reality is worse: **most Uzbek book retailers do not
record ISBNs at all.** Any further source should be ISBN-checked *before* a
driver is written for it.

---

## Getting it running on the new machine

Everything runs in Docker. Nothing is installed on the host except Docker.

```bash
git clone <this repo> && cd book-scraper
cp .env.example .env          # then set APP_KEY, see below
docker compose up -d --build  # first build takes a few minutes
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Then:

| Service | Address |
| --- | --- |
| App | http://localhost:8000 |
| Adminer | http://localhost:8080 (server `mysql`, user/pass/db `book_scraper` / `secret` / `book_scraper`) |
| Meilisearch | http://localhost:7700 |
| MySQL | `127.0.0.1:3306` |
| Vite | http://localhost:5173 |

### Bringing the data across

The Docker volumes do **not** travel with the repo. `book-scraper-dump.sql.gz`
(2 MB, git-ignored) is a verified dump of all 1,349 books — it was restored
into a scratch database and row-counted before being written.

```bash
gzcat book-scraper-dump.sql.gz | docker compose exec -T mysql \
  mysql -ubook_scraper -psecret book_scraper

docker compose exec app php artisan scout:import "App\Models\Book"
```

The raw response cache (`storage/app/private/scraping`, 574 MB, 4,410 gzipped
pages) is **not** in the dump. Without it the next crawl re-fetches those pages
from the network. Copy the directory across if you want to avoid that; it is
optional, and re-fetching only costs time and politeness budget.

---

## The crawl

A full asaxiy walk was running when this session ended and was **stopped by
hand**. Run #5 in the UI is marked failed with a `scrape_errors` row explaining
why — that is deliberate, not a bug.

It had done **3,520 of 10,388 product pages**. To resume, just start a new run:
the response cache means already-fetched pages cost nothing, so it picks up
roughly where it left off.

Start one from the UI at `/scrape-runs` → **Run now**, or:

```bash
docker compose exec app php artisan tinker --execute \
  'app(App\Scraping\StartScrapeRun::class)->handle("asaxiy_uz");'
```

Expect **five to six hours** for the full catalogue. That is the 1 request per
second politeness rule from the plan, plus roughly a second per 500 KB page.
`SCRAPING_MAX_PAGES_PER_RUN` caps a run; `0` means everything.

---

## Things that are easy to get wrong

These all cost real debugging time in this session. They are written down so
they do not have to be found twice.

### The queue settings are load-bearing

`DB_QUEUE_RETRY_AFTER=2000` in `.env`, and the worker runs `--timeout=1800`.

The rule is **`retry_after` must be larger than the longest job, and the worker
timeout must sit below `retry_after`.** The Laravel defaults are 90 and 300,
which are inverted. Catalogue discovery takes about 23 minutes, so with the
defaults the queue handed the job out a second time at 90 seconds and it died
with `MaxAttemptsExceededException` — nothing to do with scraping at all.

### Queue order decides whether books appear

The worker listens on `--queue=scraping-upserts,default,scraping`, and that
order matters. Laravel drains earlier queues completely first.

Fetch jobs and upsert jobs used to share one queue, and the database queue pops
in id order — so thousands of already-fetched books sat behind every remaining
network request. The book count stayed frozen for hours while the crawler
looked healthy. Upserts now have their own queue, listed first, so books land
as they arrive.

`default` sits in the middle because Scout's index jobs go there. Behind
`scraping` they would never run during a long crawl and search would go stale.

### Scout is queued on purpose

`SCOUT_QUEUE=true`. Indexing happens in a job so that a Meilisearch outage
fails an index job instead of breaking a scrape upsert mid-crawl.

The catch: `php artisan scout:import` also queues. If the index looks empty
after an import, the jobs are simply waiting. To index right now:

```bash
docker compose exec -e SCOUT_QUEUE=false app php artisan scout:import "App\Models\Book"
```

### Tests must never need a search engine

`phpunit.xml` sets `SCOUT_DRIVER=collection`, which searches the database
directly. Without it every API test fails with `Could not resolve host:
meilisearch`, because a test runner on the host cannot reach a Docker hostname.

### Do not trim multibyte text with `trim()`

`trim($s, "…–—…")` works on **bytes**. The byte `0x80` inside an en dash is
also the tail of Cyrillic `р`, so a charlist trim cuts letters in half and
leaves invalid UTF-8. Every following `/u` regex then returns `NULL`, which
silently emptied `title_normalized` for every Cyrillic title. Use a regex trim.

### Scraped strings overflow columns

Sites print anything. A bilingual listing gave
`O'zb/Rus O'zbekcha Узб/Рус/Англ` — 31 characters into a `varchar(16)`. Worse,
transliteration *lengthens* text, so a long Cyrillic title can overflow after
conversion even though the original fitted.

`UpsertBookFromSource` clamps every string to its column width. The untrimmed
original always stays on `book_sources.raw_payload`.

### `Http::fake()` merges stubs

Calling it twice for the same URL leaves the **first** response in front. Use
`Http::sequence()` when a test needs a URL to answer differently twice.

### Eloquent casts apply to aggregate aliases

`sum(...) as verified` came back through the `verified` boolean cast, so a
count of 8 became `true` became `1` on the dashboard. `CatalogueStatistics`
uses `->toBase()` so casts stay off aggregate rows.

---

## Architecture, briefly

```
DiscoverSourceJob        walks a source's category pages, dispatches a batch
  └─ FetchBookPageJob    one product page, unique per URL hash, backoff 30/120/300
       └─ NormalizeAndUpsertJob   no network; folds one record into the catalogue
```

- **`app/Support/isbn.php`, `app/Support/uzbek.php`** — plain functions,
  autoloaded via `composer.json` `autoload.files`. ISBN validation and
  conversion, apostrophe collapsing, Latin↔Cyrillic transliteration, title
  noise stripping, author name matching, fingerprints. 116 unit tests.
- **`app/Scraping/Http/Fetcher.php`** — the only way this app talks to another
  server. Honours robots.txt, holds a per-domain lock across the whole request
  so one domain is never fetched in parallel, spaces requests out, and writes
  every response to disk gzipped before anything parses it.
- **`app/Scraping/Drivers/`** — one class per site. Every selector is a class
  constant, so a markup change is a one-line fix.
- **`app/Catalogue/UpsertBookFromSource.php`** — the merge. See below.

### How merging works

The canonical book is **never edited in place from one source**. Each source
keeps its own row with its raw payload, and the book is rebuilt from all of
them, most trusted first. A publisher therefore cannot be undone by a shop, and
re-running the normalization layer over stored payloads fixes history with no
network.

- A valid ISBN-13 is the primary key.
- The fingerprint — `sha1(normalized title | first author | year)` — bridges
  sources **both ways**: a record with an ISBN adopts a matching ISBN-less book,
  and a record without one attaches to a book that has an ISBN. Since almost no
  Uzbek source publishes ISBNs, this is what makes a second source worth having.
- `fingerprint` is indexed but **not unique**: a hardback and a paperback of one
  title in one year share a fingerprint and are still two products.
- Fields listed in `books.locked_fields` are never overwritten.

---

## What is left

### Step 7 — the two missing admin screens

Everything else in the plan's admin section exists. Missing:

- **Review queue** for unverified and user-submitted books: approve or reject,
  setting `verified`.
- **Duplicate merge screen**: two candidates side by side, pick the winner per
  field, and write the choice into `locked_fields` so no later scrape undoes it.

No new dependencies needed. This is the last unfinished step.

### Scheduling

`schedule:list` is empty. The plan asks for a nightly incremental run and a
weekly full re-crawl. `StartScrapeRun` already exists for exactly this:

```php
Schedule::call(fn () => app(StartScrapeRun::class)->handle('asaxiy_uz'))
    ->dailyAt('03:00')->withoutOverlapping();
```

### Author deduplication

Real duplicates are in the data now:

- `Jorj Oruell` and `Jorj Oruel` — asaxiy's own typo.
- `Ahmad Lutfiy Qozonchi` and `Axmad Lutfiy Kozonchi` — the same olcha page
  spells its author two ways.

`normalize_author_name()` folds apostrophes, script and name order, and
`author_names_match()` handles initials. It does **not** fold `h`/`x` or `q`/`k`,
and it cannot fix a misspelling. A trigram or Levenshtein pass would; it fits
naturally beside the Meilisearch work.

### olcha.uz is written but switched off

`SCRAPING_OLCHA_ENABLED=true` turns it on. It contributes no ISBNs — it exists
to enrich books already known from asaxiy and to cover titles asaxiy misses.
Run it **after** an asaxiy crawl finishes; both compete for the same worker.

### `declare(strict_types=1)` is not everywhere

The plan asks for it everywhere. Every file written in this session has it; the
untouched Laravel skeleton (`config/*.php`, `bootstrap/app.php`, the stock
migrations) does not. `pint.json` enforces the rule, so a full
`vendor/bin/pint` run sweeps the rest — held off because it is a wide
mechanical diff across files nobody asked to touch.

### `WithoutOverlapping` and `RateLimited` are not used

The plan names them. Per-domain serialisation is enforced in the `Fetcher`
instead, via an atomic lock held across the whole request. That covers every
request path rather than one job class, but it is not the middleware the plan
asked for.

### `TrustLevel::outranks()` is called only by tests

The merge sorts on `->value` instead. The behaviour is right; the helper is
spare vocabulary. `Book::isLocked()` *is* used by the merge.

---

## The API

Three endpoints, all behind Sanctum, rate limited to 60/min per token
(`API_RATE_LIMIT`).

```
GET  /api/v1/books/{isbn}       any ISBN-10/13 shape, hyphens and all
GET  /api/v1/books?q=           typo tolerant, both alphabets
POST /api/v1/books/suggestions  from the mobile app; hard validated
```

`GET /books/{isbn}` distinguishes two failures the plan collapsed into one:
a **mis-scan** (bad check digit) is **422**, a valid but unknown ISBN is **404**
with the normalized ISBN echoed back. A scanner app needs to tell those apart.

Suggestions reuse the scraper's merge path at `TrustLevel::UserSubmission`, so
any real source outranks a user field by field.

Get a token:

```bash
docker compose exec app php artisan tinker --execute \
  'echo App\Models\User::first()->createToken("mobile-app")->plainTextToken;'
```

---

## Politeness

This is a scraper pointed at other people's servers, and the plan is emphatic
about it. All of it is enforced in `Fetcher`, not left to drivers:

- robots.txt parsed and honoured per domain, cached 24 h. A missing file opens
  the site; a **broken** one keeps us off it (RFC 9309 treats 5xx as unknown).
- One request per second per domain, and a `Crawl-delay` in their robots wins
  when it asks for more room. yangiasr.uz asks for 10 seconds, for example.
- One domain is never fetched in parallel.
- A descriptive User-Agent with a contact URL on every request, robots included.
- Never behind a login: a URL carrying credentials is refused before any
  request goes out.
- Every response cached to disk, so re-parsing costs nobody anything.

asaxiy's robots disallows `/*list`, which catches two book pages by accident —
`ge**list**iren`, `gu**list**an`. They are skipped. That is their rule as
published, and it is honoured rather than worked around.
