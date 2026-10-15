import { Head, Link, router } from '@inertiajs/react';
import {
  AlertTriangle,
  ArrowLeft,
  CheckCircle2,
  CreditCard,
  MapPin,
  Package,
  PackageCheck,
  Printer,
  StickyNote,
  Truck,
  User,
  Warehouse,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { deliver, index, startPreparation } from '@/routes/ecommerce/orders';

type Props = {
  order: App.Data.Ecommerce.WarehouseWebOrderDetailData;
};

const statusBadgeClasses: Record<string, string> = {
  pagado: 'border-warning-fg/25 bg-warning-bg text-warning-fg',
  en_preparacion: 'border-info-fg/25 bg-info-bg text-info-fg',
  entregado: 'border-success-fg/25 bg-success-bg text-success-fg',
};

export default function WarehouseOrderShow({ order }: Props) {
  const [checkedItems, setCheckedItems] = useState<Record<number, boolean>>({});
  const [isDeliverDialogOpen, setIsDeliverDialogOpen] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const toggleItem = (itemId: number) => {
    setCheckedItems((prev) => ({
      ...prev,
      [itemId]: !prev[itemId],
    }));
  };

  const allItemsChecked =
    order.items.length > 0 &&
    order.items.every((item) => checkedItems[item.id]);

  const handleStartPreparation = () => {
    setIsSubmitting(true);
    router.post(
      startPreparation.url(order.id),
      {},
      {
        preserveScroll: true,
        onFinish: () => setIsSubmitting(false),
      },
    );
  };

  const handleConfirmDeliver = () => {
    setIsSubmitting(true);
    router.post(
      deliver.url(order.id),
      {},
      {
        preserveScroll: true,
        onError: (errors) =>
          toast.error(
            errors.sale ??
              errors.warehouse ??
              errors.status ??
              'No se pudo confirmar la salida del pedido.',
          ),
        onFinish: () => {
          setIsSubmitting(false);
          setIsDeliverDialogOpen(false);
        },
      },
    );
  };

  const handlePrint = () => {
    window.print();
  };

  return (
    <>
      <Head
        title={`Hoja de Preparación — Pedido #${order.formatted_number} | Depósito`}
      >
        <style>{`
          @media print {
            body:has([data-warehouse-order-sheet]) [data-slot='sidebar'],
            body:has([data-warehouse-order-sheet]) [data-slot='sidebar-inset'] > header {
              display: none !important;
            }
            body:has([data-warehouse-order-sheet]) [data-slot='sidebar-inset'] {
              margin: 0;
            }
          }
        `}</style>
      </Head>

      <div
        data-warehouse-order-sheet
        className="flex flex-1 flex-col gap-6 p-4 md:p-8 print:p-0"
      >
        {/* Barra superior de navegación y acciones */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between print:hidden">
          <div className="flex items-center gap-3">
            <Button
              variant="ghost"
              size="sm"
              asChild
              className="gap-1.5 text-xs"
            >
              <Link href={index.url()}>
                <ArrowLeft className="size-4" />
                Volver a pedidos
              </Link>
            </Button>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={handlePrint}
              className="gap-1.5"
            >
              <Printer className="size-4" />
              Imprimir hoja
            </Button>

            {order.can_start_preparation && (
              <Button
                size="sm"
                onClick={handleStartPreparation}
                disabled={isSubmitting}
                className="gap-1.5 bg-primary text-primary-foreground hover:bg-primary/90"
              >
                <Package className="size-4" />
                Iniciar preparación
              </Button>
            )}

            {order.can_deliver && (
              <Button
                size="sm"
                onClick={() => setIsDeliverDialogOpen(true)}
                disabled={isSubmitting}
                className="gap-1.5 bg-primary text-primary-foreground hover:bg-primary/90"
              >
                <CheckCircle2 className="size-4" />
                {order.delivery_method === 'envio'
                  ? 'Confirmar despacho'
                  : 'Confirmar entrega'}
              </Button>
            )}
          </div>
        </div>

        {/* Encabezado del Pedido / Picking Sheet */}
        <div className="flex flex-col gap-3 rounded-lg border bg-card p-6 shadow-xs sm:flex-row sm:items-center sm:justify-between">
          <div>
            <div className="flex flex-wrap items-center gap-2">
              <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                Hoja de Preparación (Picking List)
              </span>
              <Badge
                className={`text-xs font-medium shadow-none ${
                  statusBadgeClasses[order.status] ?? ''
                }`}
              >
                {order.status_label}
              </Badge>
            </div>
            <div className="flex flex-wrap items-baseline gap-3">
              <h1 className="mt-1 font-mono text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                Pedido #{order.formatted_number}
              </h1>
              {order.sale_id && (
                <span className="text-sm font-semibold text-primary">
                  (Venta #{order.sale_id})
                </span>
              )}
            </div>
            <p className="mt-1 text-xs text-muted-foreground">
              Confirmado el {order.placed_at_formatted}
              {order.paid_at_formatted &&
                ` · Pagado el ${order.paid_at_formatted}`}
            </p>
          </div>

          <div className="flex flex-col items-start gap-1 sm:items-end">
            <Badge variant="outline" className="text-sm font-medium">
              {order.delivery_method === 'envio' ? (
                <Truck className="mr-1.5 size-4 text-primary" />
              ) : (
                <Package className="mr-1.5 size-4 text-primary" />
              )}
              {order.delivery_method_label}
            </Badge>
            <span className="text-xs text-muted-foreground">
              {order.items.length}{' '}
              {order.items.length === 1 ? 'artículo' : 'artículos'} para
              recolectar
            </span>
          </div>
        </div>

        {/* Tarjetas de Información: Cliente, Destino y Auditoría */}
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
          {/* Cliente y Entrega */}
          <Card className="border shadow-xs">
            <CardHeader className="pb-3">
              <CardTitle className="text-base font-semibold">
                Destino y Datos del Cliente
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
              <div className="flex items-start gap-2.5">
                <User className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                <div>
                  <p className="font-semibold text-foreground">
                    {order.customer_name}
                  </p>
                  {order.customer_document && (
                    <p className="text-xs text-muted-foreground">
                      {order.customer_document}
                    </p>
                  )}
                  {order.customer_phone && (
                    <p className="text-xs text-muted-foreground">
                      Tel: {order.customer_phone}
                    </p>
                  )}
                  {order.customer_email && (
                    <p className="text-xs text-muted-foreground">
                      Email: {order.customer_email}
                    </p>
                  )}
                </div>
              </div>

              <Separator />

              <div className="flex items-start gap-2.5">
                <MapPin className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                <div>
                  <p className="font-medium text-foreground">
                    {order.delivery_method === 'retiro'
                      ? 'Retiro en sucursal:'
                      : 'Dirección de entrega:'}
                  </p>
                  {order.delivery_method === 'retiro' ? (
                    <div>
                      <p className="text-xs font-semibold text-foreground">
                        {order.pickup_branch_name ?? 'Sucursal no especificada'}
                      </p>
                      {order.pickup_branch_address && (
                        <p className="text-xs text-muted-foreground">
                          {order.pickup_branch_address}
                        </p>
                      )}
                    </div>
                  ) : (
                    <div>
                      <p className="text-xs font-semibold text-foreground">
                        {order.shipping_address ?? 'Sin dirección'}
                      </p>
                      {order.shipping_notes && (
                        <p className="mt-1 text-xs text-muted-foreground italic">
                          Indicaciones de envío: {order.shipping_notes}
                        </p>
                      )}
                    </div>
                  )}
                </div>
              </div>

              {order.notes && (
                <>
                  <Separator />
                  <div className="flex items-start gap-2.5">
                    <StickyNote className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                    <div>
                      <p className="font-medium text-foreground">
                        Notas del pedido:
                      </p>
                      <p className="text-xs text-muted-foreground italic">
                        {order.notes}
                      </p>
                    </div>
                  </div>
                </>
              )}
            </CardContent>
          </Card>

          {/* Estado de Preparación y Salida */}
          <Card className="border shadow-xs">
            <CardHeader className="pb-3">
              <CardTitle className="text-base font-semibold">
                Trazabilidad y Auditoría de Depósito
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4 text-sm">
              <div className="flex items-start gap-3">
                <div className="mt-0.5 rounded-full bg-warning-bg p-1.5 text-warning-fg">
                  <CreditCard className="size-4" />
                </div>
                <div>
                  <p className="font-medium text-foreground">
                    Cobro online (Mercado Pago)
                  </p>
                  <p className="text-xs text-muted-foreground">
                    {order.paid_at_formatted
                      ? `Pagado el ${order.paid_at_formatted}`
                      : 'Pago pendiente'}
                    {order.mp_payment_id && ` · Ref MP: ${order.mp_payment_id}`}
                  </p>
                </div>
              </div>

              <div className="flex items-start gap-3">
                <div
                  className={`mt-0.5 rounded-full p-1.5 ${
                    order.preparation_started_at_formatted
                      ? 'bg-info-bg text-info-fg'
                      : 'bg-muted text-muted-foreground'
                  }`}
                >
                  <Package className="size-4" />
                </div>
                <div>
                  <p className="font-medium text-foreground">
                    Inicio de preparación
                  </p>
                  {order.preparation_started_at_formatted ? (
                    <p className="text-xs text-muted-foreground">
                      Iniciado el {order.preparation_started_at_formatted} por{' '}
                      <strong>{order.prepared_by_name ?? 'Operador'}</strong>
                    </p>
                  ) : (
                    <p className="text-xs text-muted-foreground">
                      Aún no iniciado por depósito
                    </p>
                  )}
                </div>
              </div>

              <div className="flex items-start gap-3">
                <div
                  className={`mt-0.5 rounded-full p-1.5 ${
                    order.delivered_at_formatted
                      ? 'bg-success-bg text-success-fg'
                      : 'bg-muted text-muted-foreground'
                  }`}
                >
                  <CheckCircle2 className="size-4" />
                </div>
                <div>
                  <p className="font-medium text-foreground">
                    Salida de depósito (
                    {order.delivery_method === 'envio' ? 'Despacho' : 'Entrega'}
                    )
                  </p>
                  {order.delivered_at_formatted ? (
                    <p className="text-xs text-muted-foreground">
                      Confirmado el {order.delivered_at_formatted} por{' '}
                      <strong>{order.delivered_by_name ?? 'Operador'}</strong>
                    </p>
                  ) : (
                    <p className="text-xs text-muted-foreground">
                      Pendiente de confirmación de salida
                    </p>
                  )}
                </div>
              </div>

              <div className="flex items-start gap-3">
                <div className="mt-0.5 rounded-full bg-muted p-1.5 text-muted-foreground">
                  <Warehouse className="size-4" />
                </div>
                <div>
                  <p className="font-medium text-foreground">
                    Depósito de egreso
                  </p>
                  <p className="text-xs text-muted-foreground">
                    {order.warehouse_name ?? 'Sin depósito identificado'}
                  </p>
                  {order.stock_movement ? (
                    <p className="text-xs text-muted-foreground">
                      Movimiento #{order.stock_movement.id} ·{' '}
                      {order.stock_movement.type_name} · Venta #
                      {order.stock_movement.sale_id}
                    </p>
                  ) : (
                    <p className="text-xs text-muted-foreground">
                      {order.status === 'entregado'
                        ? 'Sin movimiento de stock asociado'
                        : 'Salida de stock todavía no registrada'}
                    </p>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Tabla de Artículos a Recolectar (Picking List) */}
        <Card className="border shadow-xs">
          <CardHeader className="flex flex-row items-center justify-between pb-3">
            <div>
              <CardTitle className="text-base font-semibold">
                Artículos a recolectar
              </CardTitle>
              <CardDescription>
                Marcá los artículos recolectados en el depósito para llevar
                control físico.
              </CardDescription>
            </div>
            {order.status === 'en_preparacion' && (
              <Badge
                variant={allItemsChecked ? 'default' : 'outline'}
                className="gap-1 text-xs"
              >
                <PackageCheck className="size-3.5" />
                {Object.values(checkedItems).filter(Boolean).length} de{' '}
                {order.items.length} recolectados
              </Badge>
            )}
          </CardHeader>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-[50px] text-center print:hidden">
                    Listo
                  </TableHead>
                  <TableHead className="w-[120px]">Cód. Interno</TableHead>
                  <TableHead className="w-[140px]">Cód. Barras</TableHead>
                  <TableHead>Artículo / Descripción</TableHead>
                  <TableHead className="text-center">U.M.</TableHead>
                  <TableHead className="text-right">Cantidad</TableHead>
                  <TableHead className="text-right">Precio unit.</TableHead>
                  <TableHead className="text-right">Subtotal</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {order.items.map((item) => {
                  const isChecked = !!checkedItems[item.id];

                  return (
                    <TableRow
                      key={item.id}
                      className={isChecked ? 'bg-success-bg/15' : undefined}
                    >
                      <TableCell className="text-center print:hidden">
                        <Checkbox
                          checked={isChecked}
                          onCheckedChange={() => toggleItem(item.id)}
                          aria-label={`Marcar ${item.article_description}`}
                        />
                      </TableCell>
                      <TableCell className="font-mono text-xs font-semibold">
                        {item.article_internal_code}
                      </TableCell>
                      <TableCell className="font-mono text-xs text-muted-foreground">
                        {item.article_barcode ?? '—'}
                      </TableCell>
                      <TableCell>
                        <div className="font-medium text-foreground">
                          {item.article_description}
                        </div>
                      </TableCell>
                      <TableCell className="text-center text-xs text-muted-foreground">
                        {item.unit_of_measure_abbreviation}
                      </TableCell>
                      <TableCell className="text-right font-mono text-base font-bold text-foreground">
                        {item.quantity}
                      </TableCell>
                      <TableCell className="text-right text-xs text-muted-foreground">
                        {item.formatted_unit_price}
                      </TableCell>
                      <TableCell className="text-right font-medium text-foreground">
                        {item.formatted_line_total}
                      </TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>

            {/* Resumen de Importes */}
            <div className="flex flex-col items-end gap-1.5 border-t p-4 sm:flex-row sm:items-center sm:justify-between">
              <div className="text-xs text-muted-foreground">
                Los precios unitarios corresponden al momento en que el pedido
                fue confirmado en la tienda online.
              </div>
              <div className="w-full space-y-1 text-right sm:w-auto">
                <div className="flex justify-between gap-8 text-xs text-muted-foreground">
                  <span>Subtotal artículos:</span>
                  <span className="font-medium text-foreground">
                    {order.formatted_items_amount}
                  </span>
                </div>
                {Number(order.shipping_cost) > 0 && (
                  <div className="flex justify-between gap-8 text-xs text-muted-foreground">
                    <span>Costo de envío:</span>
                    <span className="font-medium text-foreground">
                      {order.formatted_shipping_cost}
                    </span>
                  </div>
                )}
                <div className="flex justify-between gap-8 border-t pt-1 text-base font-bold text-foreground">
                  <span>Total pedido:</span>
                  <span className="text-primary">
                    {order.formatted_total_amount}
                  </span>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Modal de confirmación de entrega / despacho */}
        <Dialog
          open={isDeliverDialogOpen}
          onOpenChange={(open) => {
            if (!open) {
              setIsDeliverDialogOpen(false);
            }
          }}
        >
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-foreground">
                <CheckCircle2 className="size-5 text-primary" />
                Confirmar{' '}
                {order.delivery_method === 'envio' ? 'despacho' : 'entrega'} de
                pedido #{order.formatted_number}
              </DialogTitle>
              <DialogDescription>
                Se registrará la salida física definitiva de los artículos del
                depósito.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-3 py-2 text-sm">
              <div className="rounded-md border border-warning-fg/25 bg-warning-bg p-3 text-warning-fg">
                <div className="flex items-start gap-2">
                  <AlertTriangle className="mt-0.5 size-4 shrink-0 text-warning-fg" />
                  <div className="text-xs">
                    <p className="font-semibold">Baja de stock automática:</p>
                    <p className="mt-0.5">
                      Al confirmar, se creará un movimiento inmutable de{' '}
                      <strong>Salida por Entrega de Pedido Web</strong> y se
                      descontará el stock de los {order.items.length} artículos
                      en el depósito online.
                    </p>
                  </div>
                </div>
              </div>

              {!allItemsChecked && (
                <div className="rounded-md border border-info-fg/25 bg-info-bg p-3 text-info-fg">
                  <p className="text-xs">
                    <strong>Nota:</strong> No todos los artículos tienen el
                    casillero de verificación marcado en la hoja de picking.
                    Podés confirmar la salida igualmente si ya recolectaste la
                    mercadería.
                  </p>
                </div>
              )}
            </div>

            <DialogFooter className="gap-2 sm:gap-0">
              <Button
                variant="outline"
                onClick={() => setIsDeliverDialogOpen(false)}
                disabled={isSubmitting}
              >
                Cancelar
              </Button>
              <Button
                onClick={handleConfirmDeliver}
                disabled={isSubmitting}
                className="bg-primary font-medium text-primary-foreground hover:bg-primary/90"
              >
                {isSubmitting
                  ? 'Confirmando...'
                  : order.delivery_method === 'envio'
                    ? 'Confirmar despacho'
                    : 'Confirmar entrega'}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </>
  );
}
