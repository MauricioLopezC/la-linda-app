import { Head, Link, router, useForm } from '@inertiajs/react';
import {
  AlertTriangle,
  ArrowLeft,
  Ban,
  Building2,
  Calendar,
  CheckCircle2,
  Download,
  Pencil,
  Truck,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency } from '@/lib/utils';
import { dashboard } from '@/routes';
import { cancel, edit, index, issue, pdf } from '@/routes/purchasing/orders';
import type { BreadcrumbItem } from '@/types';

type OrderData = App.Data.Purchasing.PurchaseOrderData;

type Props = {
  order: OrderData;
};

const statusClasses: Record<string, string> = {
  borrador:
    'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
  emitida:
    'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
  cancelada:
    'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300',
};

export default function PurchaseOrderShow({ order }: Props) {
  const [isCancelModalOpen, setIsCancelModalOpen] = useState(false);
  const [isIssuing, setIsIssuing] = useState(false);

  const cancelForm = useForm({
    reason: '',
  });

  const handleIssue = () => {
    if (
      !confirm(
        '¿Confirmás la emisión de esta orden de compra? Al emitirse quedará inmutable.',
      )
    ) {
      return;
    }

    setIsIssuing(true);
    router.post(
      issue.url({ purchase_order: order.id }),
      {},
      {
        onSuccess: () => {
          toast.success('Orden de compra emitida correctamente');
        },
        onError: () => {
          toast.error('No se pudo emitir la orden de compra');
        },
        onFinish: () => setIsIssuing(false),
      },
    );
  };

  const handleCancel = (e: React.FormEvent) => {
    e.preventDefault();

    if (!cancelForm.data.reason.trim()) {
      toast.error('Ingresá el motivo de la cancelación');

      return;
    }

    cancelForm.post(cancel.url({ purchase_order: order.id }), {
      onSuccess: () => {
        setIsCancelModalOpen(false);
        cancelForm.reset();
        toast.success('Orden de compra cancelada correctamente');
      },
      onError: () => {
        toast.error('Ocurrió un error al cancelar la orden');
      },
    });
  };

  return (
    <>
      <Head title={`Orden de compra ${order.order_number}`} />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        {/* Encabezado y acciones */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="space-y-1">
            <Button variant="ghost" size="sm" asChild className="mb-2 gap-1">
              <Link href={index.url()}>
                <ArrowLeft className="size-4" />
                Volver a órdenes de compra
              </Link>
            </Button>
            <div className="flex items-center gap-3">
              <Heading
                title={`Orden de compra ${order.order_number}`}
                description={`Emitida para ${order.supplier_name}`}
              />
              <Badge
                variant="outline"
                className={`px-3 py-1 text-sm font-semibold uppercase ${statusClasses[order.status] ?? ''}`}
              >
                {order.status_label}
              </Badge>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            {order.can_edit && (
              <Button variant="outline" asChild className="gap-2">
                <Link href={edit.url({ purchase_order: order.id })}>
                  <Pencil className="size-4" />
                  Editar borrador
                </Link>
              </Button>
            )}

            {order.can_issue && (
              <Button
                onClick={handleIssue}
                disabled={isIssuing}
                className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700"
              >
                <CheckCircle2 className="size-4" />
                Emitir orden
              </Button>
            )}

            {order.can_cancel && (
              <Button
                variant="destructive"
                onClick={() => setIsCancelModalOpen(true)}
                className="gap-2"
              >
                <Ban className="size-4" />
                Cancelar orden
              </Button>
            )}

            <Button variant="outline" asChild className="gap-2">
              <a
                href={pdf.url({ purchase_order: order.id })}
                target="_blank"
                rel="noopener noreferrer"
              >
                <Download className="size-4" />
                Descargar PDF
              </a>
            </Button>
          </div>
        </div>

        {/* Alerta si está cancelada */}
        {order.status === 'cancelada' && (
          <div className="flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 p-4 text-rose-900 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-200">
            <AlertTriangle className="mt-0.5 size-5 shrink-0 text-rose-600" />
            <div>
              <h4 className="text-sm font-semibold">
                Esta orden de compra fue cancelada
              </h4>
              <p className="mt-1 text-xs">
                <strong>Motivo:</strong>{' '}
                {order.cancellation_reason ?? 'Sin motivo registrado.'}
              </p>
              <p className="mt-0.5 text-xs text-rose-700 dark:text-rose-400">
                Cancelada el {order.cancelled_at ?? '-'} por{' '}
                {order.cancelled_by_name ?? 'Usuario del sistema'}.
              </p>
            </div>
          </div>
        )}

        {/* Información general */}
        <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
          <Card className="border bg-card shadow-xs">
            <CardHeader className="pb-2">
              <CardTitle className="flex items-center gap-2 text-sm font-semibold text-muted-foreground uppercase">
                <Truck className="size-4 text-primary" />
                Proveedor adjudicado
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-1.5 text-sm">
              <div className="text-base font-semibold">
                {order.supplier_name}
              </div>
              <div className="font-mono text-xs text-muted-foreground">
                CUIT: {order.supplier_tax_id}
              </div>
              {order.payment_terms && (
                <div className="mt-2 border-t pt-2 text-xs">
                  <span className="font-medium text-muted-foreground">
                    Condición comercial:
                  </span>{' '}
                  {order.payment_terms}
                </div>
              )}
            </CardContent>
          </Card>

          <Card className="border bg-card shadow-xs">
            <CardHeader className="pb-2">
              <CardTitle className="flex items-center gap-2 text-sm font-semibold text-muted-foreground uppercase">
                <Building2 className="size-4 text-primary" />
                Lugar de entrega
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-1.5 text-sm">
              <div className="text-base font-semibold">
                {order.warehouse_name}
              </div>
              <div className="text-xs text-muted-foreground">
                Supermercados La Linda S.A.
              </div>
              {order.expected_delivery_date_formatted && (
                <div className="mt-2 border-t pt-2 text-xs">
                  <span className="font-medium text-muted-foreground">
                    Entrega esperada:
                  </span>{' '}
                  <span className="font-semibold text-foreground">
                    {order.expected_delivery_date_formatted}
                  </span>
                </div>
              )}
            </CardContent>
          </Card>

          <Card className="border bg-card shadow-xs">
            <CardHeader className="pb-2">
              <CardTitle className="flex items-center gap-2 text-sm font-semibold text-muted-foreground uppercase">
                <Calendar className="size-4 text-primary" />
                Fechas y registro
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-1.5 text-sm">
              <div>
                <span className="text-xs text-muted-foreground">
                  Fecha de emisión:
                </span>{' '}
                <span className="font-medium">
                  {order.issue_date_formatted}
                </span>
              </div>
              <div>
                <span className="text-xs text-muted-foreground">
                  Registrada por:
                </span>{' '}
                <span className="font-medium">
                  {order.user_name ?? 'Administración'}
                </span>
              </div>
              <div className="border-t pt-2 text-xs text-muted-foreground">
                {order.status === 'emitida'
                  ? 'Documento emitido formalmente e inmutable.'
                  : order.status === 'borrador'
                    ? 'En preparación. Podés editar el detalle antes de emitir.'
                    : 'Orden cancelada.'}
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Tabla de artículos */}
        <Card className="border bg-card shadow-xs">
          <CardHeader className="border-b pb-3">
            <div className="flex items-center justify-between">
              <div>
                <CardTitle className="text-base font-semibold">
                  Detalle de artículos solicitados
                </CardTitle>
                <p className="mt-0.5 text-xs text-muted-foreground">
                  Precios pactados y cantidades acordadas con el proveedor.
                </p>
              </div>
              <span className="rounded bg-muted px-2.5 py-1 text-xs font-semibold text-muted-foreground">
                {order.items.length}{' '}
                {order.items.length === 1 ? 'renglón' : 'renglones'}
              </span>
            </div>
          </CardHeader>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-12 text-center">#</TableHead>
                  <TableHead className="w-32">Código</TableHead>
                  <TableHead>Descripción del artículo</TableHead>
                  <TableHead className="w-24 text-center">U.M.</TableHead>
                  <TableHead className="w-32 text-right">Cantidad</TableHead>
                  <TableHead className="w-36 text-right">
                    Precio unitario
                  </TableHead>
                  <TableHead className="w-40 text-right">Subtotal</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {order.items.length === 0 ? (
                  <TableRow>
                    <TableCell
                      colSpan={7}
                      className="py-8 text-center text-muted-foreground"
                    >
                      No hay artículos registrados en esta orden.
                    </TableCell>
                  </TableRow>
                ) : (
                  order.items.map((item, index) => (
                    <TableRow key={item.id}>
                      <TableCell className="text-center text-xs text-muted-foreground">
                        {index + 1}
                      </TableCell>
                      <TableCell className="font-mono text-xs font-semibold">
                        {item.article_internal_code}
                      </TableCell>
                      <TableCell className="font-medium">
                        {item.article_description}
                      </TableCell>
                      <TableCell className="text-center text-xs text-muted-foreground">
                        {item.unit_of_measure}
                      </TableCell>
                      <TableCell className="text-right font-mono">
                        {item.quantity}
                      </TableCell>
                      <TableCell className="text-right font-mono">
                        {formatCurrency(item.unit_price)}
                      </TableCell>
                      <TableCell className="text-right font-mono font-semibold">
                        {formatCurrency(item.line_total)}
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </CardContent>

          {/* Resumen de totales */}
          <div className="border-t bg-muted/20 p-4">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div className="max-w-md text-xs text-muted-foreground">
                {order.notes ? (
                  <div>
                    <span className="font-semibold text-foreground">
                      Observaciones:
                    </span>{' '}
                    {order.notes}
                  </div>
                ) : (
                  <span>Sin observaciones adicionales informadas.</span>
                )}
              </div>

              <div className="flex flex-col items-end gap-1">
                <div className="flex items-center gap-3">
                  <span className="text-sm font-semibold text-muted-foreground uppercase">
                    Total general de la orden:
                  </span>
                  <span className="font-mono text-2xl font-bold text-primary">
                    {formatCurrency(order.total_amount)}
                  </span>
                </div>
                <span className="text-xs text-muted-foreground italic">
                  Suma neta de subtotales pactados. Sin impuestos calculados en
                  esta etapa.
                </span>
              </div>
            </div>
          </div>
        </Card>

        {/* Modal de Cancelación */}
        <Dialog open={isCancelModalOpen} onOpenChange={setIsCancelModalOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>
                Cancelar orden de compra {order.order_number}
              </DialogTitle>
              <DialogDescription>
                Esta acción cancelará la orden de compra emitida. Las órdenes
                canceladas permanecen en el sistema con fines de auditoría pero
                no tienen efecto alguno sobre el stock.
              </DialogDescription>
            </DialogHeader>

            <form onSubmit={handleCancel} className="space-y-4 py-2">
              <div className="space-y-1.5">
                <Label htmlFor="cancellation_reason">
                  Motivo de cancelación{' '}
                  <span className="text-destructive">*</span>
                </Label>
                <Textarea
                  id="cancellation_reason"
                  placeholder="Explicá el motivo de cancelación..."
                  rows={3}
                  value={cancelForm.data.reason}
                  onChange={(e) => cancelForm.setData('reason', e.target.value)}
                  required
                />
                <InputError message={cancelForm.errors.reason} />
              </div>

              <DialogFooter>
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setIsCancelModalOpen(false)}
                >
                  Cerrar
                </Button>
                <Button
                  type="submit"
                  variant="destructive"
                  disabled={cancelForm.processing}
                >
                  Confirmar cancelación
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      </div>
    </>
  );
}

PurchaseOrderShow.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Compras', href: '#' },
    { title: 'Órdenes de compra', href: index() },
    { title: 'Detalle', href: '#' },
  ] satisfies BreadcrumbItem[],
};
