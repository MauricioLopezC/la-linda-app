<?php

namespace App\Actions\Purchasing;

use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CreateSupplierVoucher
{
    use ConvertsMoneyToCents;

    public function __construct(private ResolveSupplierVoucherStatus $resolveStatus) {}

    /**
     * @param  array{
     *     supplier_id: int,
     *     type: string,
     *     letter: string,
     *     point_of_sale: string,
     *     number: string,
     *     issue_date: string,
     *     due_date: ?string,
     *     net_amount: string,
     *     other_taxes_amount: string,
     *     notes: ?string
     * }  $data
     */
    public function handle(array $data): SupplierVoucher
    {
        return DB::transaction(function () use ($data): SupplierVoucher {
            $supplier = Supplier::query()->active()->find($data['supplier_id']);

            if ($supplier === null) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'El proveedor seleccionado no existe o está inactivo.',
                ]);
            }

            $type = SupplierVoucherType::from($data['type']);
            $letter = SupplierVoucherLetter::from($data['letter']);
            $netCents = $this->moneyToCents($data['net_amount']);
            $otherTaxesCents = $this->moneyToCents($data['other_taxes_amount']);
            $vatCents = $letter->discriminatesVat()
                ? intdiv(($netCents * 21) + 50, 100)
                : 0;
            $totalCents = $netCents + $vatCents + $otherTaxesCents;

            if ($totalCents <= 0 || $totalCents > 999_999_999_999) {
                throw ValidationException::withMessages([
                    'net_amount' => 'El importe total calculado debe ser mayor a cero y no superar $ 9.999.999.999,99.',
                ]);
            }

            $vatAmount = $this->centsToMoney($vatCents);
            $totalAmount = $this->centsToMoney($totalCents);
            $status = $this->resolveStatus->handle($type, $totalAmount, $totalAmount);

            $voucher = SupplierVoucher::create([
                'supplier_id' => $supplier->id,
                'type' => $type,
                'letter' => $letter,
                'point_of_sale' => $data['point_of_sale'],
                'number' => $data['number'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'net_amount' => $data['net_amount'],
                'vat_amount' => $vatAmount,
                'other_taxes_amount' => $data['other_taxes_amount'],
                'total_amount' => $totalAmount,
                'status' => $status,
                'notes' => $data['notes'],
            ]);

            Log::info('Supplier voucher created', [
                'supplier_voucher_id' => $voucher->id,
                'supplier_id' => $supplier->id,
                'fiscal_number' => $voucher->letter->value.' '.$voucher->point_of_sale.'-'.$voucher->number,
                'user_id' => auth()->id(),
            ]);

            return $voucher->load('supplier');
        });
    }
}
