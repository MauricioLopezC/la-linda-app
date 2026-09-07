<?php

namespace App\Http\Requests\Purchasing;

use App\Enums\Catalog\ArticleStatus;
use App\Enums\Purchasing\PurchaseOrderStatus;
use App\Models\Catalog\Article;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\Supplier;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'order_number' => ! empty($this->input('order_number')) ? trim((string) $this->input('order_number')) : null,
            'payment_terms' => ! empty($this->input('payment_terms')) ? trim((string) $this->input('payment_terms')) : null,
            'expected_delivery_date' => ! empty($this->input('expected_delivery_date')) ? (string) $this->input('expected_delivery_date') : null,
            'notes' => ! empty($this->input('notes')) ? trim((string) $this->input('notes')) : null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $supplierTable = (new Supplier)->getTable();
        $warehouseTable = (new Warehouse)->getTable();
        $orderTable = (new PurchaseOrder)->getTable();
        $articleTable = (new Article)->getTable();

        return [
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists($supplierTable, 'id')->where(
                    fn (Builder $query): Builder => $query->where('is_active', true)
                ),
            ],
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists($warehouseTable, 'id')->where(
                    fn (Builder $query): Builder => $query->where('is_active', true)
                ),
            ],
            'order_number' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique($orderTable, 'order_number'),
            ],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'issue_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'status' => ['nullable', Rule::in([PurchaseOrderStatus::Draft->value, PurchaseOrderStatus::Issued->value])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['nullable', 'array'],
            'items.*.article_id' => [
                'required',
                'integer',
                Rule::exists($articleTable, 'id')->where(
                    fn (Builder $query): Builder => $query->where('status', ArticleStatus::Active->value)
                ),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_id.required' => 'El proveedor es obligatorio.',
            'supplier_id.exists' => 'El proveedor seleccionado no existe o está inactivo.',
            'warehouse_id.required' => 'El depósito de destino es obligatorio.',
            'warehouse_id.exists' => 'El depósito seleccionado no existe o está inactivo.',
            'issue_date.required' => 'La fecha de emisión es obligatoria.',
            'expected_delivery_date.after_or_equal' => 'La fecha esperada de entrega no puede ser anterior a la fecha de emisión.',
            'items.*.article_id.required' => 'El artículo es obligatorio en cada renglón.',
            'items.*.article_id.exists' => 'El artículo seleccionado no existe o no está activo.',
            'items.*.quantity.required' => 'La cantidad es obligatoria.',
            'items.*.quantity.gt' => 'La cantidad debe ser mayor a cero.',
            'items.*.unit_price.required' => 'El precio unitario es obligatorio.',
            'items.*.unit_price.gt' => 'El precio unitario debe ser mayor a cero.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $items = $this->input('items', []);
            if (! is_array($items)) {
                return;
            }

            $status = $this->input('status', PurchaseOrderStatus::Draft->value);
            if ($status === PurchaseOrderStatus::Issued->value && count($items) === 0) {
                $v->errors()->add('items', 'No se puede emitir una orden de compra sin al menos un artículo.');
            }

            $seen = [];
            foreach ($items as $index => $item) {
                if (! isset($item['article_id'])) {
                    continue;
                }
                $articleId = $item['article_id'];
                if (in_array($articleId, $seen, true)) {
                    $v->errors()->add("items.{$index}.article_id", 'Un artículo no puede repetirse dentro de la misma orden de compra.');
                }
                $seen[] = $articleId;
            }
        });
    }
}
