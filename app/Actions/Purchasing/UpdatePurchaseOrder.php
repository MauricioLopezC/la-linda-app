<?php

namespace App\Actions\Purchasing;

use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Catalog\ArticleStatus;
use App\Enums\Purchasing\PurchaseOrderStatus;
use App\Models\Catalog\Article;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Models\Purchasing\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UpdatePurchaseOrder
{
    use ConvertsMoneyToCents;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(PurchaseOrder $purchaseOrder, array $data): PurchaseOrder
    {
        if (! $purchaseOrder->canBeEdited()) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden modificar órdenes de compra en estado borrador. Las órdenes emitidas o canceladas son inmutables.',
            ]);
        }

        return DB::transaction(function () use ($purchaseOrder, $data): PurchaseOrder {
            $supplier = Supplier::findOrFail((int) $data['supplier_id']);
            if (! $supplier->is_active) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'El proveedor seleccionado no está activo.',
                ]);
            }

            $warehouse = Warehouse::findOrFail((int) $data['warehouse_id']);
            if (! $warehouse->is_active) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'El depósito seleccionado no está activo.',
                ]);
            }

            if (isset($data['expected_delivery_date']) && $data['expected_delivery_date'] !== '') {
                if ($data['expected_delivery_date'] < $data['issue_date']) {
                    throw ValidationException::withMessages([
                        'expected_delivery_date' => 'La fecha esperada de entrega no puede ser anterior a la fecha de emisión.',
                    ]);
                }
            }

            $itemsData = $data['items'] ?? [];
            if (! is_array($itemsData)) {
                $itemsData = [];
            }

            $status = isset($data['status']) && $data['status'] === PurchaseOrderStatus::Issued->value
                ? PurchaseOrderStatus::Issued
                : PurchaseOrderStatus::Draft;

            if ($status === PurchaseOrderStatus::Issued && count($itemsData) === 0) {
                throw ValidationException::withMessages([
                    'items' => 'No se puede emitir una orden de compra sin al menos un artículo.',
                ]);
            }

            $purchaseOrder->update([
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'payment_terms' => isset($data['payment_terms']) && $data['payment_terms'] !== '' ? (string) $data['payment_terms'] : null,
                'issue_date' => $data['issue_date'],
                'expected_delivery_date' => isset($data['expected_delivery_date']) && $data['expected_delivery_date'] !== '' ? (string) $data['expected_delivery_date'] : null,
                'notes' => isset($data['notes']) && $data['notes'] !== '' ? (string) $data['notes'] : null,
                'status' => $status,
            ]);

            // Replace items cleanly
            $purchaseOrder->items()->delete();

            $seenArticles = [];
            $totalCents = 0;

            $articleIds = array_map(fn (array $item): int => (int) $item['article_id'], $itemsData);
            $articles = Article::whereIn('id', $articleIds)->get()->keyBy('id');

            foreach ($itemsData as $index => $itemData) {
                $articleId = (int) $itemData['article_id'];

                if (isset($seenArticles[$articleId])) {
                    throw ValidationException::withMessages([
                        "items.{$index}.article_id" => 'Un artículo no puede repetirse dentro de la misma orden de compra.',
                    ]);
                }
                $seenArticles[$articleId] = true;

                $article = $articles->get($articleId);
                if (! $article) {
                    throw ValidationException::withMessages([
                        "items.{$index}.article_id" => 'El artículo seleccionado no existe.',
                    ]);
                }

                if ($article->status !== ArticleStatus::Active) {
                    throw ValidationException::withMessages([
                        "items.{$index}.article_id" => "El artículo '{$article->description}' no está activo.",
                    ]);
                }

                $quantity = (float) $itemData['quantity'];
                $unitPrice = (float) $itemData['unit_price'];

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => 'La cantidad debe ser mayor a cero.',
                    ]);
                }

                if ($unitPrice <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.unit_price" => 'El precio unitario debe ser mayor a cero.',
                    ]);
                }

                $lineTotalFloat = round($quantity * $unitPrice, 2);
                $lineTotalStr = number_format($lineTotalFloat, 2, '.', '');
                $lineCents = $this->moneyToCents($lineTotalStr);
                $totalCents += $lineCents;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'article_id' => $article->id,
                    'quantity' => number_format($quantity, 3, '.', ''),
                    'unit_price' => number_format($unitPrice, 2, '.', ''),
                    'line_total' => $lineTotalStr,
                ]);
            }

            $purchaseOrder->update([
                'total_amount' => $this->centsToMoney($totalCents),
            ]);

            Log::info(sprintf(
                'Purchase order updated [ID: %d, Number: %s, Status: %s, Total: %s] by User ID: %s',
                $purchaseOrder->id,
                $purchaseOrder->order_number,
                $purchaseOrder->status->value,
                $purchaseOrder->total_amount,
                auth()->id() ?? 'system'
            ));

            return $purchaseOrder;
        });
    }
}
