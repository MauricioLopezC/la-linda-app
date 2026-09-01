<?php

namespace App\Models\Purchasing;

use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherStatus;
use App\Enums\Purchasing\SupplierVoucherType;
use Database\Factories\Purchasing\SupplierVoucherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_id
 * @property SupplierVoucherType $type
 * @property SupplierVoucherLetter $letter
 * @property string $point_of_sale
 * @property string $number
 * @property Carbon $issue_date
 * @property Carbon|null $due_date
 * @property string $net_amount
 * @property string $vat_amount
 * @property string $other_taxes_amount
 * @property string $total_amount
 * @property SupplierVoucherStatus $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Supplier $supplier
 */
#[Fillable([
    'supplier_id',
    'type',
    'letter',
    'point_of_sale',
    'number',
    'issue_date',
    'due_date',
    'net_amount',
    'vat_amount',
    'other_taxes_amount',
    'total_amount',
    'status',
    'notes',
])]
class SupplierVoucher extends Model
{
    /** @use HasFactory<SupplierVoucherFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'letter' => SupplierVoucherLetter::A->value,
        'other_taxes_amount' => '0.00',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => SupplierVoucherType::class,
            'letter' => SupplierVoucherLetter::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'net_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'other_taxes_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'status' => SupplierVoucherStatus::class,
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function pendingBalance(): string
    {
        return $this->total_amount;
    }

    public function isOverdue(?Carbon $referenceDate = null): bool
    {
        return $this->due_date !== null
            && $this->due_date->isBefore($referenceDate ?? today())
            && (float) $this->pendingBalance() > 0;
    }
}
