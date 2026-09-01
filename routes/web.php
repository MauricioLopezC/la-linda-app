<?php

use App\Http\Controllers\Catalog\ArticleController;
use App\Http\Controllers\Catalog\BrandController;
use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\UnitOfMeasureController;
use App\Http\Controllers\Inventory\StockAdjustmentController;
use App\Http\Controllers\Inventory\StockConsultationController;
use App\Http\Controllers\Inventory\StockMovementHistoryController;
use App\Http\Controllers\Inventory\StockParameterController;
use App\Http\Controllers\Inventory\WarehouseController;
use App\Http\Controllers\Organization\BranchController;
use App\Http\Controllers\Pricing\VatRateController;
use App\Http\Controllers\Purchasing\SupplierController;
use App\Http\Controllers\Purchasing\SupplierVoucherController;
use App\Http\Controllers\Sales\PaymentMethodController;
use App\Http\Controllers\Sales\PointOfSaleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('catalog/categories')->name('catalog.categories.')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::put('{category}', [CategoryController::class, 'update'])->name('update');
        Route::patch('{category}/toggle', [CategoryController::class, 'toggleStatus'])->name('toggle');
    });

    Route::prefix('catalog/brands')->name('catalog.brands.')->group(function () {
        Route::get('/', [BrandController::class, 'index'])->name('index');
        Route::post('/', [BrandController::class, 'store'])->name('store');
        Route::put('{brand}', [BrandController::class, 'update'])->name('update');
        Route::patch('{brand}/toggle', [BrandController::class, 'toggleStatus'])->name('toggle');
    });

    Route::prefix('catalog/units-of-measure')->name('catalog.units-of-measure.')->group(function () {
        Route::get('/', [UnitOfMeasureController::class, 'index'])->name('index');
        Route::post('/', [UnitOfMeasureController::class, 'store'])->name('store');
        Route::put('{unit_of_measure}', [UnitOfMeasureController::class, 'update'])->name('update');
        Route::patch('{unit_of_measure}/toggle', [UnitOfMeasureController::class, 'toggleStatus'])->name('toggle');
    });

    Route::prefix('catalog/articles')->name('catalog.articles.')->group(function () {
        Route::get('/', [ArticleController::class, 'index'])->name('index');
        Route::post('/', [ArticleController::class, 'store'])->name('store');
        Route::put('{article}', [ArticleController::class, 'update'])->name('update');
        Route::delete('{article}', [ArticleController::class, 'destroy'])->name('destroy');
    });

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

    Route::prefix('inventory/stocks')->name('inventory.stocks.')->group(function () {
        Route::get('/', [StockConsultationController::class, 'index'])->name('index');
    });

    Route::prefix('inventory/adjustments')->name('inventory.adjustments.')->group(function () {
        Route::get('create', [StockAdjustmentController::class, 'create'])->name('create');
        Route::get('articles', [StockAdjustmentController::class, 'searchArticles'])->name('articles');
        Route::post('/', [StockAdjustmentController::class, 'store'])->name('store');
        Route::get('{stock_movement}', [StockAdjustmentController::class, 'show'])->name('show');
    });

    Route::prefix('inventory/movements')->name('inventory.movements.')->group(function () {
        Route::get('/', [StockMovementHistoryController::class, 'index'])->name('index');
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
    });

    Route::prefix('pricing/vat-rates')->name('pricing.vat-rates.')->group(function () {
        Route::get('/', [VatRateController::class, 'index'])->name('index');
        Route::post('/', [VatRateController::class, 'store'])->name('store');
        Route::put('{vat_rate}', [VatRateController::class, 'update'])->name('update');
        Route::patch('{vat_rate}/toggle', [VatRateController::class, 'toggleStatus'])->name('toggle');
    });

    Route::prefix('sales/payment-methods')->name('sales.payment-methods.')->group(function () {
        Route::get('/', [PaymentMethodController::class, 'index'])->name('index');
        Route::post('/', [PaymentMethodController::class, 'store'])->name('store');
        Route::put('{payment_method}', [PaymentMethodController::class, 'update'])->name('update');
        Route::patch('{payment_method}/toggle', [PaymentMethodController::class, 'toggleStatus'])->name('toggle');
    });

    Route::prefix('purchasing/suppliers')->name('purchasing.suppliers.')->group(function () {
        Route::get('/', [SupplierController::class, 'index'])->name('index');
        Route::post('/', [SupplierController::class, 'store'])->name('store');
        Route::put('{supplier}', [SupplierController::class, 'update'])->name('update');
        Route::patch('{supplier}/toggle', [SupplierController::class, 'toggleStatus'])->name('toggle');
        Route::delete('{supplier}', [SupplierController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('purchasing/vouchers')->name('purchasing.vouchers.')->group(function () {
        Route::get('/', [SupplierVoucherController::class, 'index'])->name('index');
        Route::get('create', [SupplierVoucherController::class, 'create'])->name('create');
        Route::post('/', [SupplierVoucherController::class, 'store'])->name('store');
        Route::get('{supplier_voucher}/pdf', [SupplierVoucherController::class, 'pdf'])->name('pdf');
    });
});

require __DIR__.'/settings.php';
