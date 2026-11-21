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

A web app (Laravel 13, Livewire 4, Tailwind 4, PHPUnit) for a company that rents vehicle garages by the day or month. The full spec is `docs/requirements.md` (Indonesian) and the agreed schema is `docs/erd.md`, with its table notes, delete behavior and constants in the "ERD notes" section of `.claude/agents/backend.md`. When they disagree with this file, the docs win. Read them before changing domain logic.

## Roles
- **Admin**: created by a seeder only, never self-registered. Creates garages in batches, verifies new tenants, extends rentals, uploads extension payment proof, and sees reports.
- **Tenant (Penyewa)**: self-registers (NIK, name, phone, email as username, password). Status is `pending`, `approved` or `rejected`. Only `approved` tenants can log in.

## Garage and vehicle rules
- Garage types: bicycle (code `S001`), car (`C001`), electric car (`E001`). The number is sequential per type, maximum 999. A batch that would pass 999 is rejected entirely and nothing is created.
- Bicycles and motorcycles only fit bicycle garages. Cars only fit car garages. Electric cars fit car or electric garages.
- Plate number is required for every vehicle type except bicycle.
- `daily_price` lives on each garage and is set per batch. Monthly price is always 25 x the daily price.

## Rental rules
- Payment is always upfront. The tenant uploads the payment proof, and it is stored for reference only (no admin confirmation).
- Start date defaults to today and the tenant may choose a later date. Availability is computed from date overlap. There is no stored "used" status, and a garage is free again the day after the end date.
- Daily rental maximum is (days in the start month - 1). A monthly rental is exactly one month long, counted from the start date.
- A tenant can rent several garages at once.
- Rentals copy the prices and charging numbers they used, so changing prices or settings later never changes an old invoice.

## Electric car charging
- Applies only to an electric car in an electric garage. An electric car in a normal car garage has no charging fee.
- The tenant enters the current battery in kW (not a percentage) once, when renting.
- Charging amount = (max kW - current kW) x price per kWh. Example: max 10, current 2, so 8 x price.
- Price per kWh, max kW and charging speed (1 kW/hour) are one global row in `charging_settings`, edited by admin in the app. Speed is only used to show estimated charging time.
- The invoice shows rent and charging as two separate amounts.

## Extensions
- Only admin extends, only the end date changes, and it can only be moved later, never earlier. There is no maximum number of days.
- Extra cost = added days x the rental's daily price, for daily and monthly rentals.
- An extension is blocked if it overlaps the next booking on the same garage.
- Admin uploads the payment proof. Each extension is stored in `rental_extensions`, and `rentals.end_date` always holds the latest end date.

## Reports
Per month in a year, per day in a month, per garage type and per tenant. Each shows the number of rentals and total income (rent, charging and extensions).

## Conventions specific to this project
- All table ids and foreign keys are UUIDs (`uuid('id')->primary()`, `foreignUuid`). Models need the `HasUuids` trait.
- Allowed values (role, status, type) are `public const` strings in `final class Const_<Name>` files in `app/Utilities/Constants/` (same style as `Const_Date`). Constants are grouped by kind: roles in `Const_Role`, statuses in `Const_Status`, types in `Const_Type`. Names in `Const_Type` carry a prefix for what they describe, for example `Const_Type::VEHICLE_CAR`, `Const_Type::GARAGE_ELECTRIC_CAR` and `Const_Type::RENTAL_DAILY`. Do not use PHP enums or database `enum` columns. Columns are plain strings, and migrations and code reference the constants. A default in a migration is fine when it has a reason, for example `->default(Const_Status::PENDING)`.
- Every migration decision (foreign key delete behavior, `nullable`, `unique`, `default`, non-obvious column types) needs a short comment saying why. See "Migration rules" in `.claude/agents/backend.md`.
- Tests are PHPUnit classes (`php artisan make:test`, `--unit` for unit tests). Pest is not installed.
- Use the project's subagents in `.claude/agents` (`backend`, `fe-design`, `software-architecture`) when the task matches their scope.
