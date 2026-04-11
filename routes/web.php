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

    Route::livewire('suppliers', 'pages::suppliers.index')->name('suppliers.index');
    Route::livewire('procurement/rfqs', 'pages::procurement.rfqs.index')->name('procurement.rfqs.index');
    Route::livewire('procurement/rfqs/create', 'pages::procurement.rfqs.create')->name('procurement.rfqs.create');
    Route::livewire('procurement/rfqs/{id}/edit', 'pages::procurement.rfqs.edit')->name('procurement.rfqs.edit');
    Route::livewire('procurement/rfqs/{id}', 'pages::procurement.rfqs.show')->name('procurement.rfqs.show');
    Route::livewire('procurement/purchase-orders', 'pages::procurement.purchase-orders.index')->name('procurement.purchase-orders.index');
    Route::livewire('procurement/purchase-orders/create', 'pages::procurement.purchase-orders.create')->name('procurement.purchase-orders.create');
    Route::livewire('procurement/purchase-orders/{id}', 'pages::procurement.purchase-orders.show')->name('procurement.purchase-orders.show');
    Route::livewire('procurement/purchase-orders/{id}/receive', 'pages::procurement.purchase-orders.receive')->name('procurement.purchase-orders.receive');
});

require __DIR__.'/settings.php';
