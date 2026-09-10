<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Running with Docker

### Local development

```bash
cp .env.example .env      # only the first time
docker compose up -d --build
```

The app is served at http://localhost:8000 and Vite runs at http://localhost:5173
with hot module replacement. The first start waits for MySQL, then creates
`.env`'s app key and the database schema for you.

The stack runs seven containers:

| Service     | What it does                                         |
| ----------- | ---------------------------------------------------- |
| `app`       | php-fpm, and the only container that runs migrations |
| `web`       | nginx on port 8000                                   |
| `mysql`     | MySQL 8.4 on port 3306, stored in a named volume     |
| `vite`      | Vite dev server on port 5173                         |
| `adminer`   | Database browser on port 8080                        |
| `queue`     | `queue:listen`, so job code changes are picked up    |
| `scheduler` | `schedule:work`                                      |

Run commands inside the containers:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan test
docker compose exec app composer install
docker compose exec vite npm install <package>
```

### Browsing the database

Adminer is at http://localhost:8080. The server field is already filled in with
`mysql`; sign in with the `DB_USERNAME`, `DB_PASSWORD` and `DB_DATABASE` values
from `.env` (`book_scraper` / `secret` / `book_scraper` by default).

MySQL is also published on port 3306, so a desktop client can connect to
`127.0.0.1` with the same credentials. Change the published port with
`FORWARD_DB_PORT` if 3306 is already taken on your machine.

Adminer is a development tool and is deliberately not part of `compose.prod.yaml`.

Useful overrides:

- `APP_PORT`, `VITE_PORT` and `ADMINER_PORT` change the published ports.
- `XDEBUG_MODE=debug docker compose up -d app` turns Xdebug on; it is off by default.
- On Linux, set `UID` and `GID` to your own ids before building so bind-mounted
  files stay writable: `UID=$(id -u) GID=$(id -g) docker compose up -d --build`.

### Production

```bash
docker compose -f compose.prod.yaml up -d --build
```

This builds a self-contained image: PHP dependencies without dev packages, the
Vite build output, and the config, route and view caches warmed on boot. Source
files are baked in, not mounted.

`APP_KEY`, `DB_PASSWORD` and `DB_ROOT_PASSWORD` must be set in the environment
or in `.env`, or the stack refuses to start. MySQL data lives in a `mysql_data`
volume and the application's own files in a `storage` volume. To use a database
server you already run, point `DB_HOST` at it and drop the `mysql` service.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
