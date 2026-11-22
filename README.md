# Garage Rental App

A web app for a company that rents vehicle garages by the day or by the month. There are three garage
types (bicycle, car, electric car), electric cars can pay for EV charging after the rental has started,
and there are two roles: admin and tenant.

Stack: Laravel 13, Livewire 4 (Alpine is bundled), Tailwind CSS 4, Vite, PHPUnit. Pages are Livewire
components, there are no controllers.

The full spec is in [docs/requirements.md](docs/requirements.md) (Indonesian) and the database
schema is in [docs/erd.md](docs/erd.md).

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# configure DB_* in .env (MySQL, or keep the default sqlite), then:
php artisan migrate:fresh --seed
php artisan storage:link   # needed to show uploaded payment proofs (public disk)
composer run dev
```

`migrate:fresh --seed` also loads demo data: 10 tenants, 10 vehicles, 10 garages, rentals in the past,
running and upcoming, two extensions and four charging transactions. Run it again at any time to rebuild
the demo data.

## Demo logins

All passwords are `12345678`.

| Role   | Login                                    | Status                                |
|--------|------------------------------------------|---------------------------------------|
| Admin  | `admin@test`                             | approved                              |
| Tenant | `tenant1@test.com` to `tenant7@test.com` | approved, can log in                  |
| Tenant | `tenant8@test.com`                       | rejected, cannot log in               |
| Tenant | `tenant9@test.com`, `tenant10@test.com`  | pending, waiting for admin to approve |

## Tests

```bash
php artisan test
```

Tests are PHPUnit classes in `tests/Feature` (services, pages, auth, seeding) and run on in-memory SQLite.

## Fake today (local testing only)

To see rentals start and end, set a date in `.env` and refresh:

```
FAKE_TODAY=2026-12-05
```

The whole app then behaves as if today were that date (rental dates, garage status, reports). Change the date to move through time, and leave it empty to use the real date. It is ignored in production. While it is set, Livewire's cleanup of old temporary uploads is turned off (otherwise a fresh upload would be deleted as "old"). Rows you create get the fake date as their creation date, so reseed after testing with `php artisan migrate:fresh --seed`.

## Features

Every login lands on `/dashboard`, which shows quick links for the role of the user.

Tenant:
- Register (NIK 16 digits, name, phone, email, password) and wait for approval.
- Register and view own vehicles.
- Rent a garage (daily or monthly, start today or later) with a payment proof image (max 2 MB). The form
  lists only the free garages that fit the vehicle, with a code search and "show more".
- Add EV charging transactions to a running rental of an electric car in an electric garage, each with its own proof.
- See own rentals and charging transactions.

Admin:
- Approve or reject tenant registrations.
- Create garages in batches (type, quantity, daily price, price per kWh for electric garages).
- Extend a running rental and upload the payment proof.
- View reports for a date range: one row per day inside a single month, one row per month for a longer range (filters: start date, end date, garage type, tenant).
- View all vehicles, rentals and charging transactions (read only).

There are no edit or delete features.

## Business rules

- Only approved tenants can log in; a pending or rejected tenant gets a separate message after a correct password.
  Admins are created by the seeder only.
- Bicycles and motorcycles fit bicycle garages, cars fit car garages, electric cars fit car or electric garages.
- Plate number is required for every vehicle except bicycles and must be unique. An electric car needs a maximum capacity in kW.
- Monthly price = 25 x the daily price. The daily price is set per batch and stored on each garage.
- Start date defaults to today, a later date (booking) is allowed, a past date is not. Daily rental maximum = days in the
  start month - 1. A monthly rental lasts the days of the start month. End date = start date + days - 1.
- Payment is upfront for the rent only. A garage's status (`used` or `available`) is never stored: it is computed from rental dates (used when a rental covers today). A garage cannot
  be booked twice for overlapping dates, and a vehicle cannot be in two garages on overlapping dates.
- Prices are copied into each rental and charging transaction, so later price changes do not alter old records.
- Extension (admin only): a new row in `rentals` with status `extension`, the same rental code, starting the day after
  the previous end date. Extra cost = added days x the rental's daily price (also for monthly rentals). Only a later end
  date is allowed, not after the rental ended, and not over another booking of the garage or the vehicle.
- EV charging (tenant, separate table `charging_transactions`, linked to the original rental): allowed only while the
  rental is running (extensions count), in an electric garage, for an electric car. The tenant enters the current battery
  in kW (0 to the maximum). Charged kWh = max kW - current kW, amount = charged kWh x the garage price per kWh. A full
  battery is rejected. An electric car in a normal car garage has no charging.
- Reports count only `new` rows as rentals. Income = rent of `new` and `extension` rows (by start date) + charging
  (by the date recorded). The first allowed start date is 1 January 2026.

## Codes

- Garages: `S001` (bicycle), `C001` (car), `E001` (electric car), numbered per type up to 999. A batch that would pass 999
  is rejected as a whole.
- Rentals: `RNT-YYYYMMDD-NNNN`, for example `RNT-20261121-0001`. Extensions keep the code of their rental.
- All ids are UUIDs. The next code is the highest existing one + 1 (no counter table).

## Folder map

- `app/Livewire/` - Livewire pages (Index, Create, Show) with one folder per module.
- `app/Services/` - static services: Auth, User, Vehicle, Garage, Rental, ChargingTransaction, Report.
- `app/Models/` - Eloquent models.
- `app/Http/Middleware/` - `admin` and `approved` route middleware.
- `app/Utilities/Constants/` - allowed values (`Const_Role`, `Const_Status`, `Const_Type`, `Const_Date`).
- `resources/views/` - page views per module and shared Blade components in `components/`.
- `database/seeders/` - admin and demo data, built through the services.
- `docs/` - requirements and ERD.
