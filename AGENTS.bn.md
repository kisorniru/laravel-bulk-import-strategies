# প্রজেক্ট ওভারভিউ

- উদ্দেশ্য: বড় CSV user/customer dataset MySQL-এ import করার জন্য Laravel demo/benchmark project, যেখানে ধাপে ধাপে দ্রুততর bulk import strategy দেখানো হয়েছে।
- প্রধান executable feature: Artisan command `import:users-data`, যা `app/Console/Commands/CustomersImportCommand.php`-এ implement করা হয়েছে এবং benchmark/import helper logic `app/ImportHelper.php`-এ আছে।
- মূল business logic: `public/csv_files` থেকে CSV file পড়া, `users` table truncate করা, row import করা, এরপর timing, memory, SQL query count, এবং inserted row count print করা।
- এটি বর্তমানে পূর্ণাঙ্গ web product নয়। Web surface শুধু Laravel-এর default welcome page `/`; custom API endpoint, form workflow, dashboard, বা SEO module নেই।

# টেক স্ট্যাক

- PHP: `^8.2`.
- Framework: Laravel `^12.0`.
- Frontend: Blade + Vite `^6.2.4`, Tailwind CSS v4 via `@tailwindcss/vite`, Axios globally bootstrapped।
- Database target: `.env.example` অনুযায়ী MySQL (`DB_CONNECTION=mysql`, `DB_DATABASE=import_million_rows`)। SQLite Laravel default/testing fallback হিসেবে আছে।
- Cache: default database cache store, `cache` এবং `cache_locks` migration-backed।
- Queue: default database queue driver, standard `jobs`, `job_batches`, এবং `failed_jobs` migration সহ; application job class নেই।
- Dev/test packages: Pest 3, Laravel Pint, Pail, Sail, Tinker, Collision, Mockery, Faker।
- Third-party services: Mail/Postmark/Resend/AWS/Slack-এর default Laravel service config stub আছে; application code এগুলো ব্যবহার করছে না।

# আর্কিটেকচার সামারি

- Laravel 12 minimal skeleton, console-first import logic সহ।
- `CustomersImportCommand` `ImportHelper` trait ব্যবহার করে। Trait-টি `handle()`, file selection, truncation, benchmark setup/teardown, এবং alternative import strategy method ধারণ করে।
- Active command path: `php artisan import:users-data` CSV file prompt করে, `users` truncate করে, তারপর `handleImport($filepath)` call করে।
- `CustomersImportCommand::handleImport()`-এর বেশিরভাগ আগের strategy commented note হিসেবে রাখা আছে। Active strategy হলো MySQL `LOAD DATA LOCAL INFILE`।
- `ImportHelper`-এ private benchmark strategy `import01...import12...` আছে; `handleImport()` পরিবর্তন না করলে এগুলো selectable নয়।
- Service/repository layer, DTO, request validator, event/listener, policy/gate, এবং API response abstraction বর্তমানে নেই।

# ফোল্ডার স্ট্রাকচার ব্যাখ্যা

- `app/Console/Commands/CustomersImportCommand.php`: registered Artisan command এবং active import implementation।
- `app/ImportHelper.php`: command-এ ব্যবহৃত trait; lifecycle, CSV prompt, benchmarking, এবং reusable import strategy experiment handle করে।
- `app/Models/User.php`: default authenticatable user model, যেখানে `company`, `city`, `country`, এবং `birthday` fillable field যোগ করা হয়েছে।
- `database/migrations`: default Laravel users/password reset/sessions/cache/queue table, extra user profile column সহ।
- `public/csv_files`: import benchmark-এর intended source CSV fixture। এই checkout-এ file-গুলো Git LFS pointer text বলে মনে হচ্ছে, real CSV payload নয়।
- `routes/web.php`: শুধু `/` route, যা `welcome` view return করে।
- `routes/console.php`: শুধু default `inspire` closure command।
- `resources/views/welcome.blade.php`: default Laravel landing page, Vite fallback inline CSS সহ।
- `resources/js` এবং `resources/css`: default Vite/Tailwind entrypoint।
- `tests`: default Pest example test মাত্র।

# কোডিং কনভেনশন

- Laravel defaults: PSR-4 namespace `App\\`, controller `App\Http\Controllers`-এর নিচে, model `App\Models`-এর নিচে।
- Import strategy method explicit numbered name ব্যবহার করে, যেমন `import06LazyCollectionWithChunkingAndPdo()`, এবং compact benchmark comment থাকে।
- Current import code speed-critical bulk insert-এর জন্য Eloquent-এর বদলে direct `DB`/PDO operation পছন্দ করে।
- CSV row positional ধরে নেওয়া হয়েছে; helper strategy-গুলোতে field সাধারণত `custom_id`, `name`, `email`, `company`, `city`, `country`, `birthday` হিসেবে ধরা হয়।
- Active command বর্তমানে শুধু ছয়টি CSV column `name`, `email`, `company`, `city`, `country`, `birthday`-এ map করে এবং literal password set করে।
- Custom validation class নেই। HTTP feature যোগ করলে inline validation-এর বদলে Laravel `FormRequest` prefer করুন।
- Established JSON response pattern নেই। API যোগ করলে অনেক endpoint বানানোর আগে ছোট, consistent response convention introduce করুন।

# অথেন্টিকেশন ও অথরাইজেশন

- Auth config default Laravel session/web guard, `App\Models\User` provider সহ।
- Login/register route, controller, middleware customization, gate, role, permission, বা policy implement করা নেই।
- `users` table-এ default auth field আছে: `email`, `email_verified_at`, `password`, `remember_token`।
- Import command user সরাসরি write করে এবং `insert`, PDO, বা `LOAD DATA` ব্যবহারের সময় model event, cast, password hashing, authorization, এবং validation bypass করে।

# ডাটাবেস কনভেনশন

- গুরুত্বপূর্ণ table:
  - `users`: `id`, nullable `name/company/city/country/birthday`, unique `email`, `password`, timestamps, auth fields।
  - `password_reset_tokens`, `sessions`: Laravel defaults।
  - `cache`, `cache_locks`: database cache defaults।
  - `jobs`, `job_batches`, `failed_jobs`: database queue defaults।
- Current migration-এ `custom_id` নেই, কিন্তু অনেক helper strategy এবং README snippet সেটি reference করে। ওই strategy enable করার আগে migration যোগ করুন।
- `AppServiceProvider` `Schema::defaultStringLength(191)` set করে, সম্ভবত older MySQL index compatibility-এর জন্য।
- `users.email` unique। Large import-এ duplicate email থাকলে data clean না করলে বা import SQL dedupe/upsert না করলে fail করবে।
- Bulk import ইচ্ছাকৃতভাবে per-row Eloquent overhead এড়িয়ে চলে; high-performance path-এ model cast/event/mutator run হবে ধরে নেবেন না।

# Cache / Queue / Jobs

- `.env.example`-এ `CACHE_STORE=database` এবং `QUEUE_CONNECTION=database`।
- Application cache call নেই।
- Queued job class নেই; import rule comment-এ স্পষ্টভাবে "No Queue" বলা আছে।
- Experimental helper/commented code-এ Laravel `Concurrency::run()` ব্যবহৃত হয়েছে, কিন্তু active command MySQL `LOAD DATA LOCAL INFILE` ব্যবহার করে।
- Composer `dev` script `php artisan queue:listen --tries=1` চালায়, কিন্তু project-এ বর্তমানে process করার মতো job নেই।

# Frontend ও SEO

- Rendering approach: শুধু Blade; Vue/React/Inertia নেই।
- Vite entrypoint: `resources/css/app.css` এবং `resources/js/app.js`।
- Tailwind v4 `resources/css/app.css`-এ `@source` path এবং `@theme` দিয়ে configured।
- `resources/js/bootstrap.js` Axios `window.axios`-এ expose করে এবং `X-Requested-With` set করে।
- SEO/meta handling minimal default HTML: charset, viewport, `<title>Laravel</title>`, এবং Bunny font preconnect। Open Graph, canonical, structured data, sitemap, বা dynamic meta layer নেই।
- Default welcome view-এ বড় inline fallback style/SVG block আছে; এটিকে application UI architecture হিসেবে ধরে নেবেন না।

# Environment ও Local Development

- Typical setup:
  - `composer install`
  - `npm install`
  - `cp .env.example .env`
  - `php artisan key:generate`
  - MySQL database `import_million_rows` configure করা
  - `php artisan migrate`
- সব dev process একসাথে চালাতে: `composer dev`, যা Laravel server, queue listener, এবং Vite concurrently start করে।
- Individual commands:
  - App server: `php artisan serve`
  - Vite dev server: `npm run dev`
  - Frontend build: `npm run build`
  - Tests: `composer test` বা `php artisan test`
  - Import benchmark: `php artisan import:users-data`
- Active `LOAD DATA LOCAL INFILE`-এর জন্য MySQL/PDO-তে local infile allow করতে হবে:
  - `.env`: `MYSQL_ATTR_LOCAL_INFILE=1`
  - MySQL server-এ `local_infile=1` অথবা `SET GLOBAL local_infile = 1;` লাগতে পারে।
- Current `.env.example` `APP_URL=http://localhost` ব্যবহার করে; `php artisan serve` default `http://127.0.0.1:8000`।

# Testing ও Debugging

- Test framework: Pest।
- Existing test শুধু Laravel skeleton smoke test; import logic cover করে না।
- `phpunit.xml` testing DB in-memory SQLite, cache/session array, queue sync set করে।
- Debug tools:
  - `php artisan test`
  - `php artisan route:list`
  - `php artisan list --raw | rg import`
  - `php artisan pail` logs-এর জন্য
  - `storage/logs/laravel.log`
- Benchmark code MySQL-specific `SHOW SESSION STATUS LIKE 'Questions'` ব্যবহার করে; SQLite/PostgreSQL-এ intended behavior হবে না।
- Import logic safely test করতে tiny real CSV fixture বানিয়ে disposable database-এ চালান, কারণ `ImportHelper::handle()` `users` truncate করে।

# গুরুত্বপূর্ণ সীমাবদ্ধতা

- `php artisan import:users-data` import করার আগে `User::truncate()` call করে। যে database-এর user preserve করা দরকার সেখানে কখনো চালাবেন না।
- `LOAD DATA LOCAL INFILE` SQL-এ file path interpolate করে। File selection trusted local path-এ সীমাবদ্ধ রাখুন; escaping/validation ছাড়া arbitrary user-controlled path pass করবেন না।
- `CustomersImportCommand`-এর active SQL-এ `password = 'default_hashed_password',`-এর পরে trailing comma আছে; real run-এ rely করার আগে verify/fix করুন।
- Active SQL literal `default_hashed_password` store করে, bcrypt hash নয়। এটি শুধু benchmark/demo data-এর জন্য acceptable।
- অনেক helper strategy `custom_id` insert করে, কিন্তু current `users` migration-এ ওই column নেই। Strategy enable করার আগে schema এবং CSV shape align করুন।
- `public/csv_files`-এর Git LFS pointer file usable CSV data নয়। Benchmark করার আগে LFS asset pull করুন বা real CSV fixture replace করুন।
- `LOAD DATA LOCAL INFILE` MySQL/MariaDB-specific এবং SQLite testing default-এ কাজ করবে না।
- Bulk insert path validation, unique handling, model event, observer, cast, এবং hashing bypass করে। Correctness গুরুত্বপূর্ণ হলে import code-এ explicit data hygiene যোগ করুন।
- `app/ImportHelper.php` modify করার সময় সতর্ক থাকুন: এতে live command lifecycle code, experimental private strategy, এবং benchmark note একসাথে আছে।

# Future Codex Session-এর Recommended Workflow

- শুরুতে `php artisan list --raw | rg import` চালান এবং `app/Console/Commands/CustomersImportCommand.php`, `app/ImportHelper.php`, `app/Models/User.php`, ও users migration পড়ুন।
- Import behavior পরিবর্তনের আগে target strategy confirm করুন: Eloquent example শিক্ষামূলক, আর PDO/`LOAD DATA` path performance-oriented।
- Import run করার আগে database disposable কিনা, real CSV data আছে কিনা, এবং `LOAD DATA` ব্যবহার করলে MySQL `local_infile` enabled কিনা verify করুন।
- Change ছোট রাখুন। Projectটি benchmark/demo, তাই explicit request ছাড়া controller, repository, queue, বা frontend UI introduce করবেন না।
- নতুন import strategy যোগ করলে method-এর কাছে benchmark result document করুন এবং row mapping/schema assumption visible রাখুন।
- Tiny fixture দিয়ে parsing/mapping SQL generation-এর test prefer করুন। Million-row file বা non-disposable MySQL state দরকার এমন test এড়িয়ে চলুন।
- HTTP/API feature যোগ করলে Laravel layer ধীরে ধীরে introduce করুন: route, controller, `FormRequest`, দরকার হলে service, feature test।
- Schema change করলে already-run migration edit না করে নতুন migration যোগ করুন।
- PHP change-এর পর `composer test` চালান; frontend asset change হলেই শুধু `npm run build` চালান।
