import { Head, Link, setLayoutProps } from '@inertiajs/react';
import {
  AlertTriangle,
  ArrowLeft,
  ArrowUpRight,
  Calendar,
  FileText,
  History,
  Printer,
  Plus,
  ShieldCheck,
  ShoppingCart,
  User,
  Warehouse,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { formatStockQuantity } from '@/lib/utils';
import {
  create as adjustmentsCreate,
  show,
} from '@/routes/inventory/adjustments';
import { index as movementsIndex } from '@/routes/inventory/movements';
import { index as stocksIndex } from '@/routes/inventory/stocks';
import { show as showSupplierVoucher } from '@/routes/purchasing/vouchers';
import { show as showSale } from '@/routes/sales/sales';
import type { BreadcrumbItem } from '@/types';

type MovementDetail = App.Data.Inventory.StockMovementDetailData;

interface Props {
  movement: MovementDetail;
}

const AUTOMATIC_HEADINGS: Record<
  string,
  { title: string; description: string }
> = {
  purchase_entry: {
    title: 'Comprobante de Entrada por Compra',
    description: 'Ingreso de mercadería por remito de proveedor',
  },
  purchase_entry_reversal: {
    title: 'Comprobante de Reversa de Compra',
    description: 'Anulación del ingreso de mercadería de un remito',
  },
  sale_exit: {
    title: 'Comprobante de Salida por Venta',
    description: 'Egreso de mercadería por venta registrada',
  },
  customer_return: {
    title: 'Comprobante de Devolución de Cliente',
    description: 'Reingreso de mercadería devuelta por un cliente',
  },
  warehouse_transfer_out: {
    title: 'Comprobante de Transferencia (Salida)',
    description: 'Egreso de mercadería hacia otro depósito',
  },
  warehouse_transfer_in: {
    title: 'Comprobante de Transferencia (Entrada)',
    description: 'Ingreso de mercadería desde otro depósito',
  },
};

const AUTOMATIC_FALLBACK_HEADING = {
  title: 'Comprobante de Movimiento de Stock',
  description: 'Movimiento generado automáticamente por el sistema',
};

const MANUAL_HEADING = {
  title: 'Comprobante de Ajuste de Stock',
  description: 'Documento respaldatorio de un ajuste manual de inventario',
};

export default function ShowStockAdjustment({ movement }: Props) {
  setLayoutProps({
    breadcrumbs: [
      {
        title: 'Inventario',
        href: '/inventory/stocks',
      },
      {
        title: 'Movimientos',
        href: movementsIndex.url(),
      },
      {
        title: `Movimiento #${movement.id}`,
        href: show.url({ stock_movement: movement.id }),
      },
    ] satisfies BreadcrumbItem[],
  });

  const heading = movement.is_automatic
    ? (AUTOMATIC_HEADINGS[movement.type_code] ?? AUTOMATIC_FALLBACK_HEADING)
    : MANUAL_HEADING;

  const handlePrint = () => {
    window.print();
  };

  return (
    <>
      <Head title={`${heading.title} #${movement.id}`} />

      <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6 print:max-w-full print:p-0">
        {/* Action buttons (hidden when printing) */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between print:hidden">
          <div className="flex items-center gap-2">
            <Button variant="outline" asChild size="sm" className="gap-1.5">
              <Link href={stocksIndex()}>
                <ArrowLeft className="h-4 w-4" />
                Volver a Existencias
              </Link>
            </Button>
          </div>

          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={handlePrint}
              className="gap-1.5"
            >
              <Printer className="h-4 w-4" />
              Imprimir Comprobante
            </Button>
            {!movement.is_automatic && (
              <Button asChild size="sm" className="gap-1.5">
                <Link href={adjustmentsCreate()}>
                  <Plus className="h-4 w-4" />
                  Nuevo Ajuste
                </Link>
              </Button>
            )}
          </div>
        </div>

        {/* Official Voucher Card */}
        <Card className="border shadow-sm print:border-none print:shadow-none">
          <CardHeader className="border-b bg-muted/30 pb-6 print:bg-transparent">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <div className="flex items-center gap-2">
                  <span className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                    Supermercados La Linda S.A.
                  </span>
                  <Badge
                    variant="outline"
                    className="gap-1 border-emerald-500/30 bg-emerald-500/10 text-emerald-600"
                  >
                    <ShieldCheck className="h-3.5 w-3.5" />
                    Movimiento Confirmado (Inmutable)
                  </Badge>
                </div>
                <CardTitle className="mt-1 text-2xl font-bold tracking-tight">
                  {heading.title}
                </CardTitle>
                <CardDescription className="text-sm">
                  {heading.description}
                </CardDescription>
              </div>

              <div className="text-left sm:text-right">
                <div className="text-xs text-muted-foreground">
                  Número de Movimiento
                </div>
                <div className="font-mono text-xl font-bold tracking-tight text-primary">
                  #{String(movement.id).padStart(6, '0')}
                </div>
                <div className="mt-0.5 text-xs text-muted-foreground">
                  {movement.created_at_formatted}
                </div>
              </div>
            </div>
          </CardHeader>

          <CardContent className="space-y-6 pt-6">
            {/* Header Metadata Grid */}
            <div className="grid grid-cols-1 gap-4 rounded-lg border bg-muted/40 p-4 sm:grid-cols-2 md:grid-cols-4 print:bg-transparent">
              <div className="space-y-1">
                <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                  <Warehouse className="h-3.5 w-3.5" />
                  <span>Depósito</span>
                </div>
                <div className="text-sm font-semibold">
                  {movement.warehouse_name}
                </div>
                <div className="text-xs text-muted-foreground">
                  {movement.branch_name}
                </div>
              </div>

              <div className="space-y-1">
                <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                  <FileText className="h-3.5 w-3.5" />
                  <span>Tipo de Movimiento</span>
                </div>
                <div className="text-sm font-semibold">
                  {movement.type_name}
                </div>
              </div>

              <div className="space-y-1">
                <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                  <User className="h-3.5 w-3.5" />
                  <span>Usuario Responsable</span>
                </div>
                <div className="text-sm font-semibold">
                  {movement.user_name}
                </div>
                <div className="text-xs text-muted-foreground">
                  ID: #{movement.user_id}
                </div>
              </div>

              <div className="space-y-1">
                <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                  <Calendar className="h-3.5 w-3.5" />
                  <span>Fecha de Registro</span>
                </div>
                <div className="text-sm font-semibold">
                  {movement.created_at_formatted.split(' ')[0]}
                </div>
                <div className="text-xs text-muted-foreground">
                  {movement.created_at_formatted.split(' ')[1]} hs
                </div>
              </div>
            </div>

            {/* Origin Voucher / Reversal Information */}
            {(movement.supplier_voucher_id ||
              movement.sale_id ||
              movement.reversal_of_movement_id ||
              movement.reversal_movement_id) && (
              <div className="flex flex-wrap items-center gap-4 rounded-lg border border-blue-200 bg-blue-50/50 p-4 text-sm dark:border-blue-900/60 dark:bg-blue-950/30">
                {movement.supplier_voucher_id && (
                  <div className="flex items-center gap-2">
                    <span className="font-semibold text-foreground">
                      Comprobante de origen:
                    </span>
                    <Button
                      variant="outline"
                      size="sm"
                      asChild
                      className="gap-1.5 bg-background font-normal"
                    >
                      <Link
                        href={showSupplierVoucher({
                          supplier_voucher: movement.supplier_voucher_id,
                        })}
                      >
                        Remito{' '}
                        {movement.supplier_voucher_formatted_number ??
                          `#${movement.supplier_voucher_id}`}
                        <ArrowUpRight className="size-3.5" />
                      </Link>
                    </Button>
                  </div>
                )}
                {movement.sale_id && (
                  <div className="flex items-center gap-2">
                    <span className="font-semibold text-foreground">
                      Venta de origen:
                    </span>
                    <Button
                      variant="outline"
                      size="sm"
                      asChild
                      className="gap-1.5 bg-background font-normal"
                    >
                      <Link href={showSale(movement.sale_id)}>
                        <ShoppingCart className="size-3.5" />
                        Venta #{movement.sale_id}
                        <ArrowUpRight className="size-3.5" />
                      </Link>
                    </Button>
                  </div>
                )}
                {movement.reversal_of_movement_id && (
                  <div className="flex items-center gap-2">
                    <span className="font-semibold text-foreground">
                      Reversa del movimiento:
                    </span>
                    <Button
                      variant="outline"
                      size="sm"
                      asChild
                      className="gap-1.5 bg-background font-normal"
                    >
                      <Link
                        href={show.url({
                          stock_movement: movement.reversal_of_movement_id,
                        })}
                      >
                        Movimiento #{movement.reversal_of_movement_id}
                        <ArrowUpRight className="size-3.5" />
                      </Link>
                    </Button>
                  </div>
                )}
                {movement.reversal_movement_id && (
                  <div className="flex items-center gap-2">
                    <span className="font-semibold text-error-fg">
                      Anulado mediante:
                    </span>
                    <Button
                      variant="outline"
                      size="sm"
                      asChild
                      className="gap-1.5 border-error-fg/30 bg-background font-normal text-error-fg"
                    >
                      <Link
                        href={show.url({
                          stock_movement: movement.reversal_movement_id,
                        })}
                      >
                        Reversa #{movement.reversal_movement_id}
                        <ArrowUpRight className="size-3.5" />
                      </Link>
                    </Button>
                  </div>
                )}
              </div>
            )}

            {/* Conflict Warning Banner */}
            {movement.has_conflict && (
              <div className="flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200">
                <AlertTriangle className="mt-0.5 size-5 shrink-0 text-amber-600 dark:text-amber-400" />
                <div className="space-y-1">
                  <p className="text-sm font-semibold">
                    Movimiento con conflicto de stock
                  </p>
                  <p className="text-xs text-amber-800 dark:text-amber-300">
                    Este egreso se registró con artículos cuya existencia previa
                    en el sistema era insuficiente o cero (el artículo físico
                    estaba en caja). La existencia resultante quedó negativa y
                    debe ser regularizada mediante un movimiento manual de stock
                    (HU-017) o ingreso de comprobante pendiente.
                  </p>
                </div>
              </div>
            )}

            {/* Observations if any */}
            {movement.notes && (
              <div className="border-l-2 border-primary bg-muted/20 py-1 pl-3 text-xs">
                <span className="font-semibold text-foreground">
                  Observaciones registradas:{' '}
                </span>
                <span className="text-muted-foreground">{movement.notes}</span>
              </div>
            )}

            {/* Detail Table */}
            <div className="overflow-hidden rounded-md border">
              <Table>
                <TableHeader className="bg-muted/50">
                  <TableRow>
                    <TableHead className="w-[80px]">Código</TableHead>
                    <TableHead>Artículo / Categoría</TableHead>
                    <TableHead className="w-[100px]">Unidad</TableHead>
                    <TableHead className="w-[150px] text-right">
                      Cantidad
                    </TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {movement.items.map((item) => {
                    const delta = parseFloat(item.quantity);

                    return (
                      <TableRow key={item.id}>
                        <TableCell className="font-mono text-xs font-semibold">
                          {item.article_internal_code}
                        </TableCell>
                        <TableCell>
                          <div className="text-sm font-medium">
                            {item.article_description}
                          </div>
                          <div className="text-xs text-muted-foreground">
                            {item.category_name}{' '}
                            {item.brand_name ? `• ${item.brand_name}` : ''}
                          </div>
                          {item.is_conflict && (
                            <div>
                              <Badge
                                variant="outline"
                                className="mt-1 gap-1 border-amber-500/30 bg-amber-500/10 text-xs text-amber-700 dark:text-amber-400"
                              >
                                <AlertTriangle className="size-3" />
                                Conflicto de stock (Stock previo:{' '}
                                {item.system_quantity ?? '0.000'})
                              </Badge>
                            </div>
                          )}
                          <Link
                            href={movementsIndex({
                              query: {
                                article_id: item.article_id,
                                warehouse_id: movement.warehouse_id,
                              },
                            })}
                            className="mt-0.5 inline-flex items-center gap-1 text-xs text-primary hover:underline print:hidden"
                          >
                            <History className="size-3" />
                            Ver kardex en {movement.warehouse_name}
                          </Link>
                        </TableCell>
                        <TableCell className="text-xs text-muted-foreground">
                          {item.unit_of_measure_name}
                        </TableCell>
                        <TableCell className="text-right font-mono text-sm">
                          {delta > 0.0001 ? (
                            <span className="font-semibold text-emerald-600">
                              +
                              {formatStockQuantity(
                                delta,
                                item.unit_of_measure_name,
                              )}
                            </span>
                          ) : delta < -0.0001 ? (
                            <span className="font-semibold text-rose-600">
                              {formatStockQuantity(
                                delta,
                                item.unit_of_measure_name,
                              )}
                            </span>
                          ) : (
                            <span className="text-muted-foreground">
                              {formatStockQuantity(
                                0,
                                item.unit_of_measure_name,
                              )}{' '}
                              (Sin cambio)
                            </span>
                          )}
                        </TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
              </Table>
            </div>

            {/* Signature Area (Visible when printing) */}
            <div className="mt-12 hidden grid-cols-2 gap-12 border-t pt-16 text-center text-xs print:grid">
              <div>
                <div className="mx-auto w-48 border-t border-dashed pt-2">
                  Firma Encargado de Depósito
                </div>
                <div className="mt-1 text-muted-foreground">
                  {movement.user_name}
                </div>
              </div>
              <div>
                <div className="mx-auto w-48 border-t border-dashed pt-2">
                  Firma Auditor / Supervisor
                </div>
                <div className="mt-1 text-muted-foreground">
                  Control de Auditoría
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </>
  );
}
