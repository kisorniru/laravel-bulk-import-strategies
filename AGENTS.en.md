# Project Overview

- Purpose: Laravel demo/benchmark project for importing large CSV user/customer datasets into MySQL using progressively faster bulk import strategies.
- Main executable feature: Artisan command `import:users-data`, implemented by `app/Console/Commands/CustomersImportCommand.php` and shared benchmarking/import helpers in `app/ImportHelper.php`.
- Primary business logic: read CSV files from `public/csv_files`, truncate `users`, import rows into `users`, then print timing, memory, SQL query count, and inserted row count.
- This is not currently a full web product. The web surface is Laravel's default welcome page at `/`; there are no custom API endpoints, form workflows, dashboards, or SEO modules.

# Tech Stack

- PHP: `^8.2`.
- Framework: Laravel `^12.0`.
- Frontend: Blade + Vite `^6.2.4`, Tailwind CSS v4 via `@tailwindcss/vite`, Axios bootstrapped globally.
- Database target: MySQL in `.env.example` (`DB_CONNECTION=mysql`, `DB_DATABASE=import_million_rows`). SQLite exists as Laravel default/testing fallback.
- Cache: database cache store by default, backed by `cache` and `cache_locks` migrations.
- Queue: database queue driver by default, with standard `jobs`, `job_batches`, and `failed_jobs` migrations; no application jobs are defined.
- Dev/test packages: Pest 3, Laravel Pint, Pail, Sail, Tinker, Collision, Mockery, Faker.
- Third-party services: only default Laravel service config stubs for Mail/Postmark/Resend/AWS/Slack; none are used by application code.

# Architecture Summary

- Laravel 12 minimal skeleton with console-first import logic.
- `CustomersImportCommand` uses the `ImportHelper` trait. The trait owns `handle()`, file selection, truncation, benchmark setup/teardown, and alternative import strategy methods.
- Active command path: `php artisan import:users-data` prompts for a CSV file, truncates `users`, then calls `handleImport($filepath)`.
- Most earlier strategies in `CustomersImportCommand::handleImport()` are preserved as commented notes. The active strategy is MySQL `LOAD DATA LOCAL INFILE`.
- `ImportHelper` also contains private benchmark strategies `import01...import12...`; these are not currently selectable unless `handleImport()` is changed to call them.
- No service/repository layer, no DTOs, no request validators, no events/listeners, no policies/gates, and no API response abstraction currently exist.

# Folder Structure Explanation

- `app/Console/Commands/CustomersImportCommand.php`: registered Artisan command and active import implementation.
- `app/ImportHelper.php`: trait used by the command; handles lifecycle, CSV prompt, benchmarking, and reusable import strategy experiments.
- `app/Models/User.php`: default authenticatable user model extended with `company`, `city`, `country`, and `birthday` fillable fields.
- `database/migrations`: default Laravel users/password reset/sessions/cache/queue tables, with extra user profile columns.
- `public/csv_files`: intended source CSV fixtures for import benchmarks. In this checkout, files appear to be Git LFS pointer text, not real CSV payloads.
- `routes/web.php`: only `/` route returning `welcome`.
- `routes/console.php`: only the default `inspire` closure command.
- `resources/views/welcome.blade.php`: default Laravel landing page with Vite fallback inline CSS.
- `resources/js` and `resources/css`: default Vite/Tailwind entrypoints.
- `tests`: default Pest example tests only.

# Coding Conventions

- Laravel defaults: PSR-4 namespace `App\\`, controllers under `App\Http\Controllers`, models under `App\Models`.
- Import strategy methods use explicit numbered names such as `import06LazyCollectionWithChunkingAndPdo()` and compact benchmark comments.
- Current import code favors direct `DB`/PDO operations over Eloquent for speed-critical bulk inserts.
- CSV rows are assumed to be positional, with fields commonly treated as `custom_id`, `name`, `email`, `company`, `city`, `country`, `birthday` in helper strategies.
- The active command currently maps only six CSV columns to `name`, `email`, `company`, `city`, `country`, `birthday`, and sets a literal password.
- There are no custom validation classes. If adding HTTP features, prefer Laravel `FormRequest` classes rather than inline validation.
- There are no established JSON response patterns. If adding APIs, introduce a small, consistent response convention before adding many endpoints.

# Authentication & Authorization

- Auth config is default Laravel session/web guard with `App\Models\User` provider.
- No login/register routes, controllers, middleware customization, gates, roles, permissions, or policies are implemented.
- `users` table includes default auth fields: `email`, `email_verified_at`, `password`, `remember_token`.
- The import command writes users directly and bypasses model events, casts, password hashing, authorization, and validation when using `insert`, PDO, or `LOAD DATA`.

# Database Conventions

- Important tables:
  - `users`: `id`, nullable `name/company/city/country/birthday`, unique `email`, `password`, timestamps, auth fields.
  - `password_reset_tokens`, `sessions`: Laravel defaults.
  - `cache`, `cache_locks`: database cache defaults.
  - `jobs`, `job_batches`, `failed_jobs`: database queue defaults.
- Current migration does not include `custom_id`, but many helper strategies and README snippets reference it. Add a migration before enabling those strategies.
- `AppServiceProvider` sets `Schema::defaultStringLength(191)`, likely for older MySQL index compatibility.
- `users.email` is unique. Large imports with duplicate emails will fail unless data is cleaned or import SQL uses dedupe/upsert behavior.
- Bulk imports intentionally avoid per-row Eloquent overhead; do not expect model casts/events/mutators to run in the high-performance paths.

# Cache / Queue / Jobs

- `CACHE_STORE=database` and `QUEUE_CONNECTION=database` in `.env.example`.
- No application cache calls are present.
- No queued job classes are present; the import rule comments explicitly say "No Queue".
- Laravel `Concurrency::run()` is used in experimental helper/commented code, but the active command uses MySQL `LOAD DATA LOCAL INFILE`.
- The Composer `dev` script starts `php artisan queue:listen --tries=1`, but this project currently has no jobs to process.

# Frontend & SEO

- Rendering approach: Blade only; no Vue/React/Inertia.
- Vite entrypoints: `resources/css/app.css` and `resources/js/app.js`.
- Tailwind v4 is configured through `resources/css/app.css` with `@source` paths and `@theme`.
- `resources/js/bootstrap.js` exposes Axios on `window.axios` and sets `X-Requested-With`.
- SEO/meta handling is minimal default HTML: charset, viewport, `<title>Laravel</title>`, and Bunny font preconnect. No Open Graph, canonical, structured data, sitemap, or dynamic meta layer.
- The default welcome view contains a large inline fallback style/SVG block; avoid treating it as application UI architecture.

# Environment & Local Development

- Typical setup:
  - `composer install`
  - `npm install`
  - `cp .env.example .env`
  - `php artisan key:generate`
  - configure MySQL database `import_million_rows`
  - `php artisan migrate`
- Run all dev processes: `composer dev` starts Laravel server, queue listener, and Vite concurrently.
- Individual commands:
  - App server: `php artisan serve`
  - Vite dev server: `npm run dev`
  - Frontend build: `npm run build`
  - Tests: `composer test` or `php artisan test`
  - Import benchmark: `php artisan import:users-data`
- For active `LOAD DATA LOCAL INFILE`, MySQL/PDO must allow local infile:
  - `.env`: `MYSQL_ATTR_LOCAL_INFILE=1`
  - MySQL server may also need `local_infile=1` or `SET GLOBAL local_infile = 1;`
- Current `.env.example` uses `APP_URL=http://localhost`; `php artisan serve` defaults to `http://127.0.0.1:8000`.

# Testing & Debugging

- Test framework: Pest.
- Existing tests are Laravel skeleton smoke tests only; they do not cover import logic.
- `phpunit.xml` sets testing DB to in-memory SQLite, cache/session to array, queue to sync.
- Debug tools:
  - `php artisan test`
  - `php artisan route:list`
  - `php artisan list --raw | rg import`
  - `php artisan pail` for logs
  - `storage/logs/laravel.log`
- The benchmark code uses MySQL-specific `SHOW SESSION STATUS LIKE 'Questions'`; it will not work as intended on SQLite/PostgreSQL.
- To test import logic safely, create tiny real CSV fixtures and run against a disposable database because `ImportHelper::handle()` truncates `users`.

# Important Constraints

- `php artisan import:users-data` calls `User::truncate()` before importing. Never run against a database with users that must be preserved.
- `LOAD DATA LOCAL INFILE` accepts a file path interpolated into SQL. Keep file selection constrained to trusted local paths; do not pass arbitrary user-controlled paths without escaping/validation.
- Active SQL in `CustomersImportCommand` has a trailing comma after `password = 'default_hashed_password',`; verify/fix before relying on the command in a real run.
- Active SQL stores a literal `default_hashed_password`, not a bcrypt hash. This is acceptable only for benchmark/demo data.
- Many helper strategies insert `custom_id`, but the current `users` migration lacks that column. Align schema and CSV shape before enabling those helpers.
- Git LFS pointer files in `public/csv_files` are not usable CSV data. Pull LFS assets or replace with real CSV fixtures before benchmarking.
- `LOAD DATA LOCAL INFILE` is MySQL/MariaDB-specific and will not work on SQLite testing defaults.
- Bulk insert paths bypass validation, unique handling, model events, observers, casts, and hashing. Add explicit data hygiene in import code if correctness matters.
- Be careful modifying `app/ImportHelper.php`: it mixes live command lifecycle code with experimental private strategies and benchmark notes.

# Recommended Workflow For Future Codex Sessions

- Start by running `php artisan list --raw | rg import` and reading `app/Console/Commands/CustomersImportCommand.php`, `app/ImportHelper.php`, `app/Models/User.php`, and the users migration.
- Confirm the target strategy before changing import behavior: Eloquent examples are educational, while PDO/`LOAD DATA` paths are performance-oriented.
- Before running imports, verify the database is disposable, real CSV data exists, and MySQL `local_infile` is enabled if using `LOAD DATA`.
- Keep changes narrow. This project is a benchmark/demo, so avoid introducing controllers, repositories, queues, or frontend UI unless explicitly requested.
- When adding an import strategy, document benchmark results near the method and keep row mapping/schema assumptions visible.
- Prefer new tests around parsing/mapping SQL generation with tiny fixtures. Avoid tests that require million-row files or non-disposable MySQL state.
- If adding HTTP/API features, introduce standard Laravel layers incrementally: route, controller, `FormRequest`, service if needed, feature test.
- If changing schema, add migrations instead of editing existing migrations after they may have been run.
- Run `composer test` after PHP changes; run `npm run build` only when frontend assets change.
