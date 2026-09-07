<?php

namespace Database\Seeders\Purchasing;

use App\Actions\Purchasing\CancelPurchaseOrder;
use App\Actions\Purchasing\CreatePurchaseOrder;
use App\Enums\Purchasing\PurchaseOrderStatus;
use App\Models\Catalog\Article;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class PurchaseOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(
        CreatePurchaseOrder $createPurchaseOrder,
        CancelPurchaseOrder $cancelPurchaseOrder
    ): void {
        $user = User::query()->first();
        $userId = $user?->id;

        $central = Warehouse::query()->where('name', 'Depósito Central')->first()
            ?? Warehouse::query()->where('is_active', true)->first();

        $norte = Warehouse::query()->where('name', 'Depósito Norte')->first()
            ?? $central;

        $ecommerce = Warehouse::query()->where('name', 'Depósito E-commerce')->first()
            ?? $central;

        if (! $central || ! $norte || ! $ecommerce) {
            return;
        }

        // Suppliers
        $arcor = Supplier::query()->where('tax_id', '30502793175')->first();
        $molinos = Supplier::query()->where('tax_id', '30500858628')->first();
        $mastellone = Supplier::query()->where('tax_id', '30500511849')->first();
        $quilmes = Supplier::query()->where('tax_id', '30500949461')->first();
        $sanCayetano = Supplier::query()->where('tax_id', '20289456121')->first();
        $unilever = Supplier::query()->where('tax_id', '30501092440')->first();

        // Articles
        $artDuraznos = Article::query()->where('internal_code', 'ART-0001')->first();
        $artChoclo = Article::query()->where('internal_code', 'ART-0002')->first();
        $artHarina = Article::query()->where('internal_code', 'ART-0003')->first();
        $artArroz = Article::query()->where('internal_code', 'ART-0004')->first();
        $artCoca = Article::query()->where('internal_code', 'ART-0005')->first();
        $artLevite = Article::query()->where('internal_code', 'ART-0006')->first();
        $artVillavicencio = Article::query()->where('internal_code', 'ART-0007')->first();
        $artLeche = Article::query()->where('internal_code', 'ART-0008')->first();
        $artAceite = Article::query()->where('internal_code', 'ART-0010')->first();
        $artFideos = Article::query()->where('internal_code', 'ART-0011')->first();
        $artCerveza = Article::query()->where('internal_code', 'ART-0012')->first();
        $artTomate = Article::query()->where('internal_code', 'ART-0013')->first();
        $artAzucar = Article::query()->where('internal_code', 'ART-0014')->first();
        $artGalletitas = Article::query()->where('internal_code', 'ART-0015')->first();

        // 1. OC-000001 - Emitida - Arcor S.A.I.C. -> Depósito Central
        if ($arcor && $artDuraznos && $artChoclo) {
            $createPurchaseOrder->handle([
                'supplier_id' => $arcor->id,
                'warehouse_id' => $central->id,
                'order_number' => 'OC-000001',
                'payment_terms' => 'Pago a 45 días fecha factura.',
                'issue_date' => today()->subDays(18)->toDateString(),
                'expected_delivery_date' => today()->subDays(3)->toDateString(),
                'notes' => 'Orden de reposición quincenal. Entregar en rampa 2, turno mañana (08:00 a 12:00 hs).',
                'status' => PurchaseOrderStatus::Issued->value,
                'items' => [
                    [
                        'article_id' => $artDuraznos->id,
                        'quantity' => '240',
                        'unit_price' => '1450.00',
                    ],
                    [
                        'article_id' => $artChoclo->id,
                        'quantity' => '360',
                        'unit_price' => '920.00',
                    ],
                ],
            ], $userId);
        }

        // 2. OC-000002 - Emitida - Molinos Río de la Plata -> Depósito Central
        if ($molinos && $artHarina && $artArroz && $artFideos) {
            $createPurchaseOrder->handle([
                'supplier_id' => $molinos->id,
                'warehouse_id' => $central->id,
                'order_number' => 'OC-000002',
                'payment_terms' => 'Pago a 30 días fecha factura.',
                'issue_date' => today()->subDays(12)->toDateString(),
                'expected_delivery_date' => today()->subDays(2)->toDateString(),
                'notes' => 'Pedido con bonificación pactada del 3% por volumen de compra mayorista.',
                'status' => PurchaseOrderStatus::Issued->value,
                'items' => [
                    [
                        'article_id' => $artHarina->id,
                        'quantity' => '500',
                        'unit_price' => '790.00',
                    ],
                    [
                        'article_id' => $artArroz->id,
                        'quantity' => '300',
                        'unit_price' => '1180.00',
                    ],
                    [
                        'article_id' => $artFideos->id,
                        'quantity' => '400',
                        'unit_price' => '650.00',
                    ],
                ],
            ], $userId);
        }

        // 3. OC-000003 - Cancelada - Mastellone Hermanos -> Depósito Central
        if ($mastellone && $artLeche) {
            $order3 = $createPurchaseOrder->handle([
                'supplier_id' => $mastellone->id,
                'warehouse_id' => $central->id,
                'order_number' => 'OC-000003',
                'payment_terms' => 'Pago semanal a 14 días.',
                'issue_date' => today()->subDays(25)->toDateString(),
                'expected_delivery_date' => today()->subDays(18)->toDateString(),
                'notes' => 'Pedido de leche fresca para distribución en sucursales.',
                'status' => PurchaseOrderStatus::Draft->value,
                'items' => [
                    [
                        'article_id' => $artLeche->id,
                        'quantity' => '1200',
                        'unit_price' => '890.00',
                    ],
                ],
            ], $userId);

            $cancelPurchaseOrder->handle(
                $order3,
                'Cancelada por actualización de lista de precios de la cuenca lechera antes del despacho.',
                $userId
            );
        }

        // 4. OC-000004 - Emitida - Cervecería Quilmes -> Depósito Central
        if ($quilmes && $artCerveza) {
            $createPurchaseOrder->handle([
                'supplier_id' => $quilmes->id,
                'warehouse_id' => $central->id,
                'order_number' => 'OC-000004',
                'payment_terms' => 'Transferencia a 30 días fecha factura.',
                'issue_date' => today()->subDays(8)->toDateString(),
                'expected_delivery_date' => today()->addDays(2)->toDateString(),
                'notes' => 'Reposición para abastecimiento de fin de semana. Paletizado estándar en cajones.',
                'status' => PurchaseOrderStatus::Issued->value,
                'items' => [
                    [
                        'article_id' => $artCerveza->id,
                        'quantity' => '600',
                        'unit_price' => '1820.00',
                    ],
                ],
            ], $userId);
        }

        // 5. OC-000005 - Emitida - Distribuidora San Cayetano -> Depósito Norte
        if ($sanCayetano && $artTomate && $artAzucar) {
            $createPurchaseOrder->handle([
                'supplier_id' => $sanCayetano->id,
                'warehouse_id' => $norte->id,
                'order_number' => 'OC-000005',
                'payment_terms' => 'Contado contra entrega con cheque propio.',
                'issue_date' => today()->subDays(6)->toDateString(),
                'expected_delivery_date' => today()->addDays(3)->toDateString(),
                'notes' => 'Entrega directa en sucursal Norte. Horario de recepción: 14:00 a 18:00 hs.',
                'status' => PurchaseOrderStatus::Issued->value,
                'items' => [
                    [
                        'article_id' => $artTomate->id,
                        'quantity' => '300',
                        'unit_price' => '610.00',
                    ],
                    [
                        'article_id' => $artAzucar->id,
                        'quantity' => '250',
                        'unit_price' => '940.00',
                    ],
                ],
            ], $userId);
        }

        // 6. OC-000006 - Borrador - Unilever de Argentina -> Depósito Central
        if ($unilever && $artAceite && $artGalletitas) {
            $createPurchaseOrder->handle([
                'supplier_id' => $unilever->id,
                'warehouse_id' => $central->id,
                'order_number' => 'OC-000006',
                'payment_terms' => 'Cuenta corriente habitual 30 días.',
                'issue_date' => today()->subDays(2)->toDateString(),
                'expected_delivery_date' => today()->addDays(10)->toDateString(),
                'notes' => 'Borrador en preparación para consolidar promociones del catálogo web.',
                'status' => PurchaseOrderStatus::Draft->value,
                'items' => [
                    [
                        'article_id' => $artAceite->id,
                        'quantity' => '180',
                        'unit_price' => '1350.00',
                    ],
                    [
                        'article_id' => $artGalletitas->id,
                        'quantity' => '220',
                        'unit_price' => '780.00',
                    ],
                ],
            ], $userId);
        }

        // 7. OC-000007 - Emitida - Arcor S.A.I.C. -> Depósito E-commerce
        if ($arcor && $artDuraznos && $artGalletitas) {
            $createPurchaseOrder->handle([
                'supplier_id' => $arcor->id,
                'warehouse_id' => $ecommerce->id,
                'order_number' => 'OC-000007',
                'payment_terms' => 'Pago a 45 días fecha factura.',
                'issue_date' => today()->subDays(1)->toDateString(),
                'expected_delivery_date' => today()->addDays(6)->toDateString(),
                'notes' => 'Stock exclusivo para canal de venta online y despacho a domicilio.',
                'status' => PurchaseOrderStatus::Issued->value,
                'items' => [
                    [
                        'article_id' => $artDuraznos->id,
                        'quantity' => '80',
                        'unit_price' => '1450.00',
                    ],
                    [
                        'article_id' => $artGalletitas->id,
                        'quantity' => '150',
                        'unit_price' => '780.00',
                    ],
                ],
            ], $userId);
        }

        // 8. OC-000008 - Borrador - Molinos Río de la Plata -> Depósito Norte
        if ($molinos && $artHarina && $artArroz) {
            $createPurchaseOrder->handle([
                'supplier_id' => $molinos->id,
                'warehouse_id' => $norte->id,
                'order_number' => 'OC-000008',
                'payment_terms' => 'Pago a 30 días fecha factura.',
                'issue_date' => today()->toDateString(),
                'expected_delivery_date' => today()->addDays(14)->toDateString(),
                'notes' => 'Borrador cargado por compras. Pendiente de confirmar cantidades finales según rotación semanal.',
                'status' => PurchaseOrderStatus::Draft->value,
                'items' => [
                    [
                        'article_id' => $artHarina->id,
                        'quantity' => '350',
                        'unit_price' => '790.00',
                    ],
                    [
                        'article_id' => $artArroz->id,
                        'quantity' => '200',
                        'unit_price' => '1180.00',
                    ],
                ],
            ], $userId);
        }

        // 9. OC-000009 - Cancelada - Distribuidora San Cayetano -> Depósito Central
        if ($sanCayetano && $artAzucar) {
            $order9 = $createPurchaseOrder->handle([
                'supplier_id' => $sanCayetano->id,
                'warehouse_id' => $central->id,
                'order_number' => 'OC-000009',
                'payment_terms' => 'Contado contra entrega.',
                'issue_date' => today()->subDays(15)->toDateString(),
                'expected_delivery_date' => today()->subDays(8)->toDateString(),
                'notes' => 'Orden complementaria de azúcar.',
                'status' => PurchaseOrderStatus::Draft->value,
                'items' => [
                    [
                        'article_id' => $artAzucar->id,
                        'quantity' => '100',
                        'unit_price' => '960.00',
                    ],
                ],
            ], $userId);

            $cancelPurchaseOrder->handle(
                $order9,
                'Cancelada por acuerdo de compra directa con el ingenio azucarero a menor costo.',
                $userId
            );
        }
    }
}
