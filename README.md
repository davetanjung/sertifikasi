# Garage Rental App

A web app for a company that rents vehicle garages by the day or by the month. There are three garage
types (bicycle, car, electric car), electric cars can pay for EV charging, and there are two roles:
admin and tenant.

Stack: Laravel 13, Livewire 4, Tailwind CSS 4, PHPUnit.

The full spec is in [docs/requirements.md](docs/requirements.md) (Indonesian) and the database
schema is in [docs/erd.md](docs/erd.md).

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# configure DB_* in .env, then:
php artisan migrate:fresh --seed
php artisan storage:link   # needed to show uploaded payment proofs
composer run dev
```

`migrate:fresh --seed` also loads demo data: 10 tenants, 10 vehicles, 10 garages, rentals in the past,
running and upcoming, two extensions and some charging transactions.

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

Tests use PHPUnit classes in `tests/Feature` and `tests/Unit`.

## Features

Tenant:
- Register (NIK, name, phone, email, password) and wait for approval.
- Manage own vehicles.
- Rent a garage (daily or monthly, start today or later) with a payment proof.
- Add EV charging sessions to a running electric garage rental.
- See own rentals and charging transactions.

Admin:
- Approve or reject tenant registrations.
- Create garages in batches (type, quantity, daily price, price per kWh for electric garages).
- Extend a running rental and upload the payment proof.
- View reports per month, per day, per garage type and per tenant.

## Business rules

- Only approved tenants can log in. Admins are created by the seeder only.
- Bicycles and motorcycles fit bicycle garages, cars fit car garages, electric cars fit car or electric garages.
- Plate number is required for every vehicle except bicycles.
- Monthly price = 25 x the daily price.
- Daily rental maximum = days in the start month - 1. A monthly rental lasts the whole start month.
- Availability is computed from dates. A garage cannot be booked twice for overlapping dates, and a vehicle
  cannot be in two garages at the same time.
- EV charging cost = (max kW - current kW) x price per kWh, only for an electric car in an electric garage.
- An extension is a new row with status `extension` and the same rental code. Extra cost = added days x daily price.
- Prices are copied into each rental, so later price changes do not alter old rentals.

## Codes

- Garages: `S001` (bicycle), `C001` (car), `E001` (electric car), numbered per type up to 999.
- Rentals: `RNT-YYYYMMDD-NNNN`, for example `RNT-20261121-0001`. Extensions keep the code of their rental.

## Folder map

- `app/Livewire/` - Livewire pages (Index, Create, Show, no edit or delete) with one folder per module.
- `app/Services/` - business logic as static services (rentals, garages, charging, reports, users, vehicles).
- `app/Models/` - Eloquent models.
- `app/Utilities/Constants/` - allowed values (`Const_Role`, `Const_Status`, `Const_Type`, `Const_Date`).
- `database/seeders/` - admin and demo data, built through the services.
- `docs/` - requirements and ERD.
