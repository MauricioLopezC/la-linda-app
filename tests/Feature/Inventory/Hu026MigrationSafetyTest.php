<?php

use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockMovementType;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Purchasing\SupplierVoucherItem;
use Illuminate\Support\Facades\Schema;

test('supplier voucher rollback aborts instead of discarding HU-026 vouchers', function () {
    $voucher = SupplierVoucher::factory()->create([
        'type' => SupplierVoucherType::Remito,
        'letter' => SupplierVoucherLetter::R,
        'status' => SupplierVoucherStatus::Confirmed,
        'due_date' => null,
        'total_amount' => '0.00',
    ]);

    $migration = require database_path(
        'migrations/2026_09_22_000001_revise_supplier_vouchers_for_hu_026.php'
    );

    expect(fn () => $migration->down())->toThrow(
        RuntimeException::class,
        'existen comprobantes incompatibles con el esquema anterior'
    );

    expect(Schema::hasColumn('supplier_vouchers', 'warehouse_id'))->toBeTrue();
    $this->assertDatabaseHas('supplier_vouchers', ['id' => $voucher->id]);
});

test('supplier voucher item rollback aborts instead of discarding zero amounts', function () {
    $item = SupplierVoucherItem::factory()->create([
        'unit_price' => '0.00',
        'line_total' => '0.00',
    ]);

    $migration = require database_path(
        'migrations/2026_09_22_000002_allow_zero_amounts_for_remito_items.php'
    );

    expect(fn () => $migration->down())->toThrow(
        RuntimeException::class,
        'existen renglones con importes incompatibles con el esquema anterior'
    );

    $this->assertDatabaseHas('supplier_voucher_items', ['id' => $item->id]);
});

test('reversal movement type rollback aborts while movements reference it', function () {
    $movementType = StockMovementType::query()
        ->where('code', StockMovementType::CODE_PURCHASE_ENTRY_REVERSAL)
        ->firstOrFail();
    $movement = StockMovement::factory()->create([
        'stock_movement_type_id' => $movementType->id,
    ]);

    $migration = require database_path(
        'migrations/2026_09_22_150857_ensure_purchase_entry_reversal_stock_movement_type_exists.php'
    );

    expect(fn () => $migration->down())->toThrow(
        RuntimeException::class,
        'existen movimientos de reversión de entradas por compra'
    );

    $this->assertDatabaseHas('stock_movement_types', ['id' => $movementType->id]);
    $this->assertDatabaseHas('stock_movements', ['id' => $movement->id]);
});
