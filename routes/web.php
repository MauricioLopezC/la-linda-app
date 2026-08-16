<?php

use App\Http\Controllers\Inventory\StockParameterController;
use App\Http\Controllers\Inventory\WarehouseController;
use App\Http\Controllers\Organization\BranchController;
use App\Http\Controllers\Sales\PointOfSaleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('organization/branches')->name('organization.branches.')->group(function () {
        Route::get('/', [BranchController::class, 'index'])->name('index');
        Route::post('/', [BranchController::class, 'store'])->name('store');
        Route::put('{branch}', [BranchController::class, 'update'])->name('update');
        Route::patch('{branch}/toggle', [BranchController::class, 'toggleStatus'])->name('toggle');
    });

    Route::prefix('inventory/warehouses')->name('inventory.warehouses.')->group(function () {
        Route::get('/', [WarehouseController::class, 'index'])->name('index');
        Route::post('/', [WarehouseController::class, 'store'])->name('store');
        Route::put('{warehouse}', [WarehouseController::class, 'update'])->name('update');
        Route::patch('{warehouse}/toggle', [WarehouseController::class, 'toggleStatus'])->name('toggle');
    });

    Route::prefix('sales/points-of-sale')->name('sales.points-of-sale.')->group(function () {
        Route::get('/', [PointOfSaleController::class, 'index'])->name('index');
        Route::post('/', [PointOfSaleController::class, 'store'])->name('store');
        Route::put('{point_of_sale}', [PointOfSaleController::class, 'update'])->name('update');
        Route::patch('{point_of_sale}/toggle', [PointOfSaleController::class, 'toggleStatus'])->name('toggle');
    });

    Route::prefix('inventory/parameters')->name('inventory.parameters.')->group(function () {
        Route::get('/', [StockParameterController::class, 'index'])->name('index');
        Route::post('movement-types', [StockParameterController::class, 'storeMovementType'])->name('movement-types.store');
        Route::put('movement-types/{movement_type}', [StockParameterController::class, 'updateMovementType'])->name('movement-types.update');
        Route::delete('movement-types/{movement_type}', [StockParameterController::class, 'destroyMovementType'])->name('movement-types.destroy');

        Route::post('adjustment-reasons', [StockParameterController::class, 'storeAdjustmentReason'])->name('adjustment-reasons.store');
        Route::put('adjustment-reasons/{adjustment_reason}', [StockParameterController::class, 'updateAdjustmentReason'])->name('adjustment-reasons.update');
        Route::delete('adjustment-reasons/{adjustment_reason}', [StockParameterController::class, 'destroyAdjustmentReason'])->name('adjustment-reasons.destroy');
        Route::patch('adjustment-reasons/{adjustment_reason}/toggle', [StockParameterController::class, 'toggleAdjustmentReason'])->name('adjustment-reasons.toggle');
    });
});

require __DIR__.'/settings.php';
