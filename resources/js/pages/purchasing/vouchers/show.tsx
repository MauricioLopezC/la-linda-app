import { Head, Link, useForm } from '@inertiajs/react';
import {
  AlertTriangle,
  ArrowLeft,
  Ban,
  CalendarClock,
  ReceiptText,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { Input } from '@/components/ui/input';
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
import { formatCurrency, formatStockQuantity } from '@/lib/utils';
import { dashboard } from '@/routes';
import { annul, index } from '@/routes/purchasing/vouchers';
import type { BreadcrumbItem } from '@/types';

type Voucher = App.Data.Purchasing.SupplierVoucherData;

const statusClasses: Record<string, string> = {
  pendiente: 'border-warning-fg/30 bg-warning-bg text-warning-fg',
  pagada_parcial: 'border-info-fg/30 bg-info-bg text-info-fg',
  pagada: 'border-success-fg/30 bg-success-bg text-success-fg',
  pendiente_imputar: 'border-info-fg/30 bg-info-bg text-info-fg',
  imputada_parcial: 'border-info-fg/30 bg-info-bg text-info-fg',
  imputada: 'border-success-fg/30 bg-success-bg text-success-fg',
  anulada: 'border-error-fg/30 bg-error-bg text-error-fg',
};

export default function SupplierVoucherShow({ voucher }: { voucher: Voucher }) {
  const [annulDialogOpen, setAnnulDialogOpen] = useState(false);
  const annulForm = useForm({ reason: '' });
  const annulErrors = annulForm.errors as Record<string, string>;

  const submitAnnulment = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    annulForm.post(annul.url({ supplier_voucher: voucher.id }), {
      onSuccess: () => {
        setAnnulDialogOpen(false);
        annulForm.reset();
        toast.success('Comprobante anulado correctamente.');
      },
      onError: () => toast.error('No se pudo anular el comprobante.'),
    });
  };

  return (
    <>
      <Head title={`${voucher.type_label} ${voucher.formatted_number}`} />
      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="flex flex-col gap-2">
            <Button variant="ghost" size="sm" asChild className="w-fit">
              <Link href={index()}>
                <ArrowLeft className="size-4" />
                Volver al listado
              </Link>
            </Button>
            <div className="flex flex-wrap items-center gap-3">
              <Heading
                title={`${voucher.type_label} ${voucher.formatted_number}`}
                description="Datos guardados del comprobante externo."
              />
              <Badge
                variant="outline"
                className={statusClasses[voucher.status] ?? ''}
              >
                {voucher.status_label}
              </Badge>
              {voucher.is_overdue && (
                <Badge
                  variant="outline"
                  className="border-error-fg/30 bg-error-bg text-error-fg"
                >
                  <CalendarClock className="size-3" />
                  Vencido
                </Badge>
              )}
            </div>
          </div>
          {voucher.can_annul && (
            <Button
              variant="destructive"
              onClick={() => setAnnulDialogOpen(true)}
            >
              <Ban className="size-4" />
              Anular comprobante
            </Button>
          )}
        </div>

        {voucher.status === 'anulada' && (
          <Alert className="border-error-fg/30 bg-error-bg text-error-fg">
            <AlertTriangle className="size-4" />
            <AlertTitle>Comprobante anulado</AlertTitle>
            <AlertDescription className="text-error-fg">
              Motivo: {voucher.annulment_reason}. Anulado el{' '}
              {voucher.annulled_at} por{' '}
              {voucher.annulled_by_name ?? 'usuario del sistema'}.
            </AlertDescription>
          </Alert>
        )}

        {voucher.is_legacy_without_items && (
          <Alert>
            <ReceiptText className="size-4" />
            <AlertTitle>Comprobante anterior al detalle</AlertTitle>
            <AlertDescription>
              Este registro se conservó sin inventar ítems que no estaban
              disponibles en la versión anterior.
            </AlertDescription>
          </Alert>
        )}

        <Card>
          <CardHeader>
            <CardTitle>Cabecera guardada</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <ReadOnlyField
              label="Proveedor"
              value={voucher.supplier_business_name}
              className="md:col-span-2"
            />
            <ReadOnlyField label="CUIT" value={voucher.supplier_tax_id} />
            <ReadOnlyField label="Tipo" value={voucher.type_label} />
            <ReadOnlyField label="Letra" value={voucher.letter} />
            <ReadOnlyField
              label="Punto de venta"
              value={voucher.point_of_sale}
            />
            <ReadOnlyField label="Número" value={voucher.number} />
            <ReadOnlyField
              label="Fecha de emisión"
              value={voucher.issue_date_formatted}
            />
            <ReadOnlyField
              label="Fecha de vencimiento"
              value={voucher.due_date_formatted ?? 'Sin vencimiento'}
            />
            <ReadOnlyField
              label="Importe total"
              value={formatCurrency(voucher.total_amount)}
            />
            <ReadOnlyField
              label="Saldo / disponible"
              value={formatCurrency(voucher.outstanding_amount)}
            />
            <div className="space-y-1.5 md:col-span-2 xl:col-span-4">
              <Label>Observaciones</Label>
              <Textarea
                readOnly
                value={voucher.notes ?? ''}
                placeholder="Sin observaciones"
                rows={3}
              />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Ítems guardados</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-14 text-center">Pos.</TableHead>
                  <TableHead>Artículo</TableHead>
                  <TableHead>Descripción original</TableHead>
                  <TableHead>Unidad</TableHead>
                  <TableHead className="text-right">Cantidad</TableHead>
                  <TableHead className="text-right">Precio unitario</TableHead>
                  <TableHead className="text-right">Importe</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {voucher.items.length === 0 ? (
                  <TableRow>
                    <TableCell
                      colSpan={7}
                      className="py-10 text-center text-muted-foreground"
                    >
                      No hay ítems históricos disponibles.
                    </TableCell>
                  </TableRow>
                ) : (
                  voucher.items.map((item) => (
                    <TableRow key={item.id}>
                      <TableCell className="text-center">
                        {item.position}
                      </TableCell>
                      <TableCell className="font-mono text-xs">
                        {item.article_internal_code ?? 'Concepto'}
                      </TableCell>
                      <TableCell className="font-medium">
                        {item.description}
                      </TableCell>
                      <TableCell>{item.unit_of_measure}</TableCell>
                      <TableCell className="text-right font-mono">
                        {formatStockQuantity(
                          Number(item.quantity),
                          item.unit_of_measure,
                        )}
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
          <div className="grid gap-3 border-t bg-muted/20 p-4 text-right sm:grid-cols-3">
            <div>
              <p className="text-xs text-muted-foreground uppercase">
                Suma de ítems
              </p>
              <p className="font-mono font-semibold">
                {formatCurrency(voucher.items_total)}
              </p>
            </div>
            <div>
              <p className="text-xs text-muted-foreground uppercase">
                Diferencia informativa
              </p>
              <p className="font-mono font-semibold">
                {formatCurrency(voucher.difference_amount)}
              </p>
            </div>
            <div>
              <p className="text-xs text-muted-foreground uppercase">
                Total del documento
              </p>
              <p className="font-mono text-xl font-bold text-primary">
                {formatCurrency(voucher.total_amount)}
              </p>
            </div>
          </div>
        </Card>
      </div>

      <Dialog open={annulDialogOpen} onOpenChange={setAnnulDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Anular comprobante</DialogTitle>
            <DialogDescription>
              La cabecera y todos los ítems se conservarán para auditoría.
            </DialogDescription>
          </DialogHeader>
          <form onSubmit={submitAnnulment} className="flex flex-col gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="reason">Motivo *</Label>
              <Textarea
                id="reason"
                rows={3}
                value={annulForm.data.reason}
                onChange={(event) =>
                  annulForm.setData('reason', event.target.value)
                }
              />
              <InputError message={annulErrors.reason ?? annulErrors.status} />
            </div>
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setAnnulDialogOpen(false)}
              >
                Cerrar
              </Button>
              <Button
                type="submit"
                variant="destructive"
                disabled={annulForm.processing}
              >
                Confirmar anulación
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}

function ReadOnlyField({
  label,
  value,
  className = '',
}: {
  label: string;
  value: string;
  className?: string;
}) {
  return (
    <div className={`space-y-1.5 ${className}`}>
      <Label>{label}</Label>
      <Input readOnly value={value} />
    </div>
  );
}

SupplierVoucherShow.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Comprobantes de proveedores', href: index() },
    { title: 'Detalle', href: '#' },
  ] satisfies BreadcrumbItem[],
};
