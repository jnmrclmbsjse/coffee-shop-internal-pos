<?php

use App\Livewire\Inventory\CountSheet;
use App\Livewire\Inventory\Movements;
use App\Livewire\Inventory\RestockStatus;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Touch-first inventory screens (Module 2). Behind the shared Filament login
// (web guard); guests are redirected to the panel login (see bootstrap/app.php).
Route::middleware('auth')->prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/opening', CountSheet::class)->defaults('phase', 'opening')->name('opening');
    Route::get('/closing', CountSheet::class)->defaults('phase', 'closing')->name('closing');
    Route::get('/status', RestockStatus::class)->name('status');
    Route::get('/movements', Movements::class)->name('movements');
});
