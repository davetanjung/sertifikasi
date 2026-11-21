<?php

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\ChargingTransaction\Create as ChargingTransactionCreate;
use App\Livewire\ChargingTransaction\Index as ChargingTransactionIndex;
use App\Livewire\Extension\Create as ExtensionCreate;
use App\Livewire\Extension\Index as ExtensionIndex;
use App\Livewire\Garage\Create as GarageCreate;
use App\Livewire\Garage\Index as GarageIndex;
use App\Livewire\Garage\Show as GarageShow;
use App\Livewire\Rental\Create as RentalCreate;
use App\Livewire\Rental\Index as RentalIndex;
use App\Livewire\Rental\Show as RentalShow;
use App\Livewire\Report\Index as ReportIndex;
use App\Livewire\User\Index as UserIndex;
use App\Livewire\User\Show as UserShow;
use App\Livewire\Vehicle\Create as VehicleCreate;
use App\Livewire\Vehicle\Index as VehicleIndex;
use App\Livewire\Vehicle\Show as VehicleShow;
use App\Services\AuthService;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/register', Register::class)->name('register');
});

Route::middleware(['auth', 'approved'])->group(function () {
    Route::post('/logout', function () {
        AuthService::logout();

        return redirect()->route('login');
    })->name('logout');

    Route::get('/vehicle', VehicleIndex::class)->name('vehicle.index');
    Route::get('/vehicle/create', VehicleCreate::class)->name('vehicle.create');
    Route::get('/vehicle/{obj}', VehicleShow::class)->name('vehicle.show');

    Route::get('/rental', RentalIndex::class)->name('rental.index');
    Route::get('/rental/create', RentalCreate::class)->name('rental.create');
    Route::get('/rental/{obj}', RentalShow::class)->name('rental.show');

    Route::get('/charging-transaction', ChargingTransactionIndex::class)->name('charging-transaction.index');
    Route::get('/charging-transaction/create', ChargingTransactionCreate::class)->name('charging-transaction.create');
});

Route::middleware(['auth', 'approved', 'admin'])->group(function () {
    Route::get('/user', UserIndex::class)->name('user.index');
    Route::get('/user/{obj}', UserShow::class)->name('user.show');

    Route::get('/garage', GarageIndex::class)->name('garage.index');
    Route::get('/garage/create', GarageCreate::class)->name('garage.create');
    Route::get('/garage/{obj}', GarageShow::class)->name('garage.show');

    Route::get('/extension', ExtensionIndex::class)->name('extension.index');
    Route::get('/extension/create', ExtensionCreate::class)->name('extension.create');

    Route::get('/report', ReportIndex::class)->name('report.index');
});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'approved'])
    ->name('dashboard');
