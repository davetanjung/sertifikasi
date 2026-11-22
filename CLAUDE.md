<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit rules ===

# PHPUnit

- This project uses PHPUnit class-based tests (not Pest closures). Create tests with `php artisan make:test {name}`, or `--unit` for unit tests.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>

# Garage Rental App

A web app (Laravel 13, Livewire 4, Tailwind 4, Vite, PHPUnit) for a company that rents vehicle garages by the day or month. The full spec is `docs/requirements.md` (Indonesian) and the agreed schema is `docs/erd.md`, with its table notes, delete behavior and constants in the "ERD notes" section of `.claude/agents/backend.md`. When they disagree with this file, the docs win. Read them before changing domain logic.

Run with `composer run dev`. `php artisan migrate:fresh --seed` rebuilds the demo data (admin `admin@test`, tenants `tenant1@test.com` to `tenant10@test.com`, all passwords `12345678`). `php artisan storage:link` is needed to show uploaded payment proofs (public disk). The default DB in `.env.example` is sqlite (MySQL also works); tests use in-memory SQLite.

## Roles
- **Admin**: created only by `AdminSeeder` (`admin@test`), never self-registered. Creates garages in batches, approves or rejects tenants, extends rentals (uploads the proof), and sees reports. Admin pages: user, garage, extension, report. Admin can also view vehicles, rentals and charging transactions, but cannot create them.
- **Tenant (Penyewa)**: self-registers (NIK 16 digits, name, phone, email as username, password). Status is `pending` (start), `approved` or `rejected`. Only `approved` tenants can log in; after a correct password, login shows a separate message for pending and rejected. There is no login throttling. A tenant sees only their own records.
- `/` redirects to `/dashboard` and every login lands there. The dashboard is role-aware (admin quick links, tenant quick links).

## Garage and vehicle rules
- Garage types: bicycle (code `S001`), car (`C001`), electric car (`E001`). The number is sequential per type (highest existing + 1, no counter table), maximum 999, and lives only in `code`. A batch that would pass 999 is rejected entirely and nothing is created.
- `daily_price` is set per batch and stored on each garage. Electric garages also have `price_per_kwh`. Monthly price is always 25 x the daily price.
- Vehicle types: bicycle, motorcycle, car, electric car. Plate is unique and required except for bicycles. An electric car has `maximum_capacity_kw`, entered by the tenant.
- Fit: bicycles and motorcycles only in bicycle garages, cars only in car garages, electric cars in car or electric garages (`GarageService::typesFor()`).
- Garage status is `used` or `available` (`Const_Status::USED` / `AVAILABLE`, the `Garage::status` accessor): never stored, computed from the rental rows (used when one covers today). Availability for given dates is computed from date overlap (`GarageService::whereFree()`). A vehicle cannot be in two garages on overlapping dates. The rental form lists only free garages (search by code, limited list with "show more").

## Rental rules
- Payment is upfront for the rent only. The tenant uploads a proof image (max 2 MB), stored for reference (no admin confirmation).
- Start date defaults to today, a later date (booking) is allowed, never a past one. A garage is free again the day after the end date.
- Daily maximum is (days in the start month - 1). A monthly rental is one month long (the days of the start month). End date = start date + days - 1.
- A tenant can rent several garages at once.
- Code format `RNT-YYYYMMDD-NNNN`. The rental copies the daily price, so changing prices later never changes an old invoice.
- `RentalService::create()` and `extend()` run in a transaction with row locks on the garage and the vehicle, so overlapping bookings cannot slip through.

## Extensions
- Admin only. An extension is a new row in `rentals` with `status = extension` and the SAME `code` as the original, starting the day after the previous end date. There is no parent id and no `rental_extensions` table: rows are matched by code (+ user and garage).
- Only the end date changes, only to a later date, and not after the rental has ended. It is blocked if it overlaps another booking of the garage or the vehicle.
- Cost = added days x the rental's daily price (also for monthly rentals). The admin uploads the proof.

## Electric car charging
- A separate table `charging_transactions`, linked to the `new` rental row. Once the rental is running, the tenant can add as many transactions as wanted, each with its own proof (`ChargingTransactionService`).
- Allowed only when the rental is active today (extensions count), the garage is electric (has `price_per_kwh`) and the vehicle is an electric car with a maximum capacity. An electric car in a normal car garage has no charging.
- The tenant enters the current battery in kW (not a percentage, 0 to the maximum). Charged kWh = max - current. Amount = charged kWh x the garage `price_per_kwh`, copied into the transaction. A full battery is rejected.
- There is no charging speed, no `charging_settings` table, and nothing is paid for charging at rental time.

## Reports
Admin only (`ReportService::forRange()`). One page with no tabs: the filters are start date, end date, garage type and tenant. A range inside one calendar month gives one row per day, a longer range gives one row per month, The page opens on today (start and end date both today). "Per month in a year" is 1 Jan to 31 Dec and "per day in a month" is the first to the last day of that month. Each row has the number of rentals (only `new` rows count), rent income (`rent_amount` of `new` and `extension` rows, grouped by start date), charging income (grouped by the date recorded) and the total. The first allowed start date is 1 January 2026.

## Conventions specific to this project
- **Pages**: no controllers. Every page is a Livewire component (Index, Create, Show; no Edit and no delete anywhere) in `app/Livewire/<Module>/`, using the traits in `App\Traits\Livewire`. Routes are at the root (`garage.index`, `rental.create`, ...) in `routes/web.php`; logout is a route closure. Middleware aliases: `admin` and `approved`. Pages that are role-specific also guard themselves in `mount()` with `abort_unless`/`abort_if`.
- **Validation**: field rules (required, format, ranges, upload) are in the component's `rules()`. Services in `app/Services` (static classes: `AuthService`, `UserService`, `VehicleService`, `GarageService`, `RentalService`, `ChargingTransactionService`, `ReportService`) hold saving and cross-record rules (availability, fit, 999 limit, extensions, charging) and throw `GeneralException`.
- **Previews**: pages never calculate prices; they show what the services return (`RentalService::preview()`, `previewExtension()`, `ChargingTransactionService::preview()`, `GarageService::nextCodes()`).
- **Uploads**: stored on the `public` disk by the component (`payment-proofs`, `extension-proofs`, `charging-proofs`); the component deletes the file when the service throws.
- **Data**: all ids are UUIDs (`uuid('id')->primary()`, `foreignUuid`). Models use `HasCoreFeature` (UUIDs, factory, route and permission helpers, keyword search); Garage and Rental also use `HasAutoNumber` for `code`. Seeders must not use `WithoutModelEvents`, because the codes are made in a creating event.
- **Constants**: allowed values (role, status, type) are `public const` strings in `final class Const_<Name>` files in `app/Utilities/Constants/` (same style as `Const_Date`). Roles in `Const_Role`, statuses in `Const_Status`, types in `Const_Type` (prefixes `VEHICLE_`, `GARAGE_`, `RENTAL_`). Do not use PHP enums or database `enum` columns. Columns are plain strings, and migrations and code reference the constants.
- **Migrations**: every decision (foreign key delete behavior, `nullable`, `unique`, `default`, non-obvious column types) needs a short comment saying why. See "Migration rules" in `.claude/agents/backend.md`.
- **Seeders**: `AdminSeeder`, `TenantSeeder`, `VehicleSeeder`, `GarageSeeder`, `RentalSeeder`, run in that order by `DatabaseSeeder`. They go through the services, so demo data follows the real rules.
- **UI** (details in `.claude/agents/fe-design.md`): monochrome (white, black, neutral grays); dark green (800/900) and dark red (800/900) only for statuses and errors; no gradients or shadows. Black sidebar, white top bar with a user dropdown (Logout). Layouts `components.layouts.app` and `components.layouts.auth`. Reuse the shared Blade components in `resources/views/components` before creating new ones. All page text is in English.
- **Tests**: PHPUnit classes. `tests/Unit` has one test class per class in `app/` (services, models, traits, middleware, helpers, Livewire pages) in folders that mirror `app/`, and tests every function. `tests/Feature` has the end-to-end page, auth and seeding tests. Run `php artisan test`. Pest is not installed.
- Use the project's subagents in `.claude/agents` (`backend`, `fe-design`, `software-architecture`) when the task matches their scope.

## Where things live
- `routes/web.php`, `bootstrap/app.php` (middleware aliases), `app/Http/Middleware/` (`EnsureUserIsAdmin`, `EnsureUserIsApproved`)
- `app/Livewire/`, `resources/views/<module>/`, `resources/views/components/`
- `app/Services/`, `app/Models/`, `app/Traits/` (+ `Traits/Livewire`), `app/Utilities/` (`Constants/`, `helpers.php`)
- `database/migrations`, `database/seeders`, `docs/`
