<?php

namespace App\Data\Purchasing;

use App\Models\Purchasing\SupplierVoucher;
use App\Models\Purchasing\VoucherApplication;
use App\Rules\Purchasing\ValidCuit;
use Spatie\LaravelData\Data;

class SupplierVoucherData extends Data
{
    /**
     * @param  array<int, SupplierVoucherItemData>  $items
     * @param  array<int, VoucherApplicationData>  $applications
     */
    public function __construct(
        public int $id,
        public int $supplier_id,
        public string $supplier_business_name,
        public string $supplier_tax_id,
        public string $type,
        public string $type_label,
        public string $letter,
        public string $point_of_sale,
        public string $number,
        public string $formatted_number,
        public string $issue_date,
        public string $issue_date_formatted,
        public ?string $due_date,
        public ?string $due_date_formatted,
        public string $total_amount,
        public string $items_total,
        public string $difference_amount,
        public string $outstanding_amount,
        public string $status,
        public string $status_label,
        public bool $is_overdue,
        public ?string $notes,
        public ?string $annulled_at,
        public ?string $annulled_by_name,
        public ?string $annulment_reason,
        public bool $can_annul,
        public bool $is_legacy_without_items,
        public array $items,
        public array $applications,
    ) {}

    public static function fromModel(SupplierVoucher $voucher): self
    {
        $voucher->loadMissing([
            'supplier',
            'items.article',
            'annulledByUser',
            'applicationsMade.targetVoucher',
            'applicationsMade.user',
            'applicationsReceived.sourceVoucher',
            'applicationsReceived.user',
        ]);

        $applications = $voucher->type->isCreditNote()
            ? $voucher->applicationsMade
                ->map(fn (VoucherApplication $application): VoucherApplicationData => VoucherApplicationData::madeByCreditNote($application))
                ->all()
            : $voucher->applicationsReceived
                ->map(fn (VoucherApplication $application): VoucherApplicationData => VoucherApplicationData::receivedByInvoice($application))
                ->all();

        return new self(
            id: $voucher->id,
            supplier_id: $voucher->supplier_id,
            supplier_business_name: $voucher->supplier->business_name,
            supplier_tax_id: ValidCuit::format($voucher->supplier->tax_id) ?? $voucher->supplier->tax_id,
            type: $voucher->type->value,
            type_label: $voucher->type->label(),
            letter: $voucher->letter->value,
            point_of_sale: $voucher->point_of_sale,
            number: $voucher->number,
            formatted_number: $voucher->letter->value.' '.$voucher->point_of_sale.'-'.$voucher->number,
            issue_date: $voucher->issue_date->toDateString(),
            issue_date_formatted: $voucher->issue_date->format('d/m/Y'),
            due_date: $voucher->due_date?->toDateString(),
            due_date_formatted: $voucher->due_date?->format('d/m/Y'),
            total_amount: (string) $voucher->total_amount,
            items_total: $voucher->itemsTotal(),
            difference_amount: $voucher->differenceAmount(),
            outstanding_amount: $voucher->outstandingAmount(),
            status: $voucher->status->value,
            status_label: $voucher->status->label(),
            is_overdue: $voucher->isOverdue(),
            notes: $voucher->notes,
            annulled_at: $voucher->annulled_at?->format('d/m/Y H:i'),
            annulled_by_name: $voucher->annulledByUser?->name,
            annulment_reason: $voucher->annulment_reason,
            can_annul: $voucher->canBeAnnulled(),
            is_legacy_without_items: $voucher->items->isEmpty(),
            items: SupplierVoucherItemData::collect($voucher->items)->all(),
            applications: $applications,
        );
    }
}
