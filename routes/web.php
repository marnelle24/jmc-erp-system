<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('organization/create', 'pages::organization.create')->name('organization.create');
});

Route::middleware(['auth', 'verified', 'tenant.context'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('products', 'pages::products.index')->name('products.index');
    Route::livewire('inventory/movements', 'pages::inventory.movements.index')->name('inventory.movements.index');
    Route::livewire('inventory/adjustments/create', 'pages::inventory.adjustments.create')->name('inventory.adjustments.create');
});

require __DIR__.'/settings.php';
