<?php

use App\Livewire\Inventory\CountSheet;
use App\Livewire\Inventory\Movements;
use App\Livewire\Inventory\RestockStatus;
use App\Livewire\Pos\CashLog;
use App\Livewire\Pos\CloseDay;
use App\Livewire\Pos\OpenDay;
use App\Livewire\Pos\OrderTaking;
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

// Touch-first POS screens (Module 3). Same shared login / touch shell as inventory.
Route::middleware('auth')->prefix('pos')->name('pos.')->group(function () {
    Route::get('/open', OpenDay::class)->name('open');
    Route::get('/order', OrderTaking::class)->name('order');
    Route::get('/cash', CashLog::class)->name('cash');
    Route::get('/close', CloseDay::class)->name('close');
});
