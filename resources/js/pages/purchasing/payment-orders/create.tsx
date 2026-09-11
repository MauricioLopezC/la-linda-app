import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Loader2, TriangleAlert, Wallet } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { invoices } from '@/actions/App/Http/Controllers/Purchasing/PaymentOrderController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency } from '@/lib/utils';
import { dashboard } from '@/routes';
import { store } from '@/routes/purchasing/payment-orders';
import type { BreadcrumbItem } from '@/types';
import InvoiceSelectionTable from './components/invoice-selection-table';
import type { InvoiceRow } from './components/invoice-selection-table';
import IssuedOrderPanel from './components/issued-order-panel';

type SupplierOption = App.Data.Purchasing.SupplierOptionData;
type PaymentMethod = App.Data.Sales.PaymentMethodData;
type SupplierVoucher = App.Data.Purchasing.SupplierVoucherListData;
type PaymentOrder = App.Data.Purchasing.PaymentOrderData;

type ItemRow = {
  supplier_voucher_id: number;
  amount_applied: string;
};

type Props = {
  suppliers: SupplierOption[];
  paymentMethods: PaymentMethod[];
  today: string;
};

/** Same input sanitiser used by orders/form.tsx: digits + one decimal separator. */
function sanitizeDecimal(value: string): string {
  let clean = value.replace(/[^0-9.,]/g, '').replace(',', '.');
  const parts = clean.split('.');

  if (parts.length > 2) {
    clean = parts[0] + '.' + parts.slice(1).join('');
  }

  return clean;
}

/** Pesos string → integer cents, so the visual total never drifts on float sums. */
function toCents(value: string): number {
  const parsed = Number(String(value).replace(',', '.'));

  return Number.isFinite(parsed) ? Math.round(parsed * 100) : 0;
}

/** Client mirror of the server rules for one line (Caso B). */
function rowAmountError(amount: string, outstanding: string): string | null {
  const trimmed = amount.trim();

  if (trimmed === '') {
    return 'Ingresá el importe a imputar.';
  }

  const cents = toCents(trimmed);

  if (cents <= 0) {
    return 'El importe debe ser mayor a cero.';
  }

  if (cents > toCents(outstanding)) {
    return 'El importe supera el saldo pendiente de la factura.';
  }

  return null;
}

export default function CreatePaymentOrder({
  suppliers = [],
  paymentMethods = [],
  today,
}: Props) {
  const page = usePage();
  // `flash` es Record<string, unknown> (genérico a propósito, ver global.d.ts) — el middleware
  // no conoce la forma de `issuedOrder`, así que la pantalla asume esa responsabilidad acá.
  const issuedOrder =
    (page.props.flash.issuedOrder as PaymentOrder | undefined) ?? null;

  const [invoicesList, setInvoicesList] = useState<SupplierVoucher[]>([]);
  const [loadingInvoices, setLoadingInvoices] = useState(false);
  const [staleWarning, setStaleWarning] = useState(false);
  const [panelDismissed, setPanelDismissed] = useState(false);

  const { data, setData, post, processing, errors, clearErrors, transform } =
    useForm<{
      supplier_id: string;
      payment_method_id: string;
      date: string;
      notes: string;
      items: ItemRow[];
    }>({
      supplier_id: '',
      payment_method_id: '',
      date: today,
      notes: '',
      items: [],
    });

  const fetchInvoices = async (supplierId: number) => {
    setLoadingInvoices(true);

    try {
      const response = await fetch(invoices.url({ supplier: supplierId }), {
        headers: { Accept: 'application/json' },
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      setInvoicesList((await response.json()) as SupplierVoucher[]);
    } catch (error) {
      console.error('Error cargando facturas del proveedor', error);
      setInvoicesList([]);
      toast.error('No se pudieron cargar las facturas del proveedor.');
    } finally {
      setLoadingInvoices(false);
    }
  };

  const handleSupplierChange = (value: string) => {
    // Cambiar de proveedor descarta las facturas elegidas (evita el Caso C).
    setData((previous) => ({ ...previous, supplier_id: value, items: [] }));
    clearErrors();
    setStaleWarning(false);
    setInvoicesList([]);

    if (value) {
      void fetchInvoices(Number(value));
    }
  };

  const toggleInvoice = (voucherId: number, checked: boolean) => {
    setData(
      'items',
      checked
        ? [
            ...data.items,
            { supplier_voucher_id: voucherId, amount_applied: '' },
          ]
        : data.items.filter((item) => item.supplier_voucher_id !== voucherId),
    );
  };

  const changeAmount = (voucherId: number, value: string) => {
    setData(
      'items',
      data.items.map((item) =>
        item.supplier_voucher_id === voucherId
          ? { ...item, amount_applied: value }
          : item,
      ),
    );
  };

  const selectedByVoucherId = useMemo(() => {
    const map = new Map<number, { amount: string; index: number }>();
    data.items.forEach((item, index) => {
      map.set(item.supplier_voucher_id, {
        amount: item.amount_applied,
        index,
      });
    });

    return map;
  }, [data.items]);

  const rows: InvoiceRow[] = useMemo(
    () =>
      invoicesList.map((voucher) => {
        const selected = selectedByVoucherId.get(voucher.id);
        const amount = selected?.amount ?? '';
        const clientError = selected
          ? rowAmountError(amount, voucher.outstanding_amount)
          : null;
        const serverError = selected
          ? errors[
              `items.${selected.index}.amount_applied` as keyof typeof errors
            ]
          : undefined;

        return {
          voucher,
          selected: selected !== undefined,
          amount,
          error: clientError ?? serverError ?? null,
        };
      }),
    [invoicesList, selectedByVoucherId, errors],
  );

  const totalCents = data.items.reduce(
    (accumulator, item) => accumulator + toCents(item.amount_applied),
    0,
  );

  const hasRowErrors = rows.some((row) => row.selected && row.error !== null);
  const allAmountsPositive = data.items.every(
    (item) => toCents(item.amount_applied) > 0,
  );

  const canSubmit =
    data.supplier_id !== '' &&
    data.payment_method_id !== '' &&
    data.date !== '' &&
    data.items.length > 0 &&
    allAmountsPositive &&
    !hasRowErrors &&
    !processing;

  const submit = () => {
    if (!canSubmit) {
      return;
    }

    transform((formData) => ({
      supplier_id: Number(formData.supplier_id),
      payment_method_id: Number(formData.payment_method_id),
      date: formData.date,
      notes: formData.notes.trim() === '' ? null : formData.notes.trim(),
      items: formData.items.map((item) => ({
        supplier_voucher_id: item.supplier_voucher_id,
        amount_applied: Number(item.amount_applied).toFixed(2),
      })),
    }));

    post(store.url(), {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Orden de pago emitida correctamente.');
        setPanelDismissed(false);
        setStaleWarning(false);
        setInvoicesList([]);
        setData({
          supplier_id: '',
          payment_method_id: '',
          date: today,
          notes: '',
          items: [],
        });
      },
      onError: (formErrors) => {
        toast.error('Revisá los datos de la orden de pago.');

        // Caso F: el saldo pudo cambiar entre que se armó el formulario y se confirmó.
        const hitItemError = Object.keys(formErrors).some((key) =>
          key.startsWith('items.'),
        );

        if (hitItemError && data.supplier_id) {
          setStaleWarning(true);
          void fetchInvoices(Number(data.supplier_id));
        }
      },
    });
  };

  const selectedPaymentMethod = paymentMethods.find(
    (method) => String(method.id) === data.payment_method_id,
  );

  return (
    <>
      <Head title="Nueva orden de pago" />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div className="space-y-1">
          <Button variant="ghost" size="sm" asChild className="mb-2 gap-1">
            <Link href={dashboard()}>
              <ArrowLeft className="size-4" />
              Volver
            </Link>
          </Button>
          <Heading
            title="Nueva orden de pago"
            description="Seleccioná un proveedor, elegí sus facturas con saldo pendiente e imputá los importes a pagar."
          />
        </div>

        {issuedOrder && !panelDismissed && (
          <IssuedOrderPanel
            order={issuedOrder}
            onDismiss={() => setPanelDismissed(true)}
          />
        )}

        {staleWarning && (
          <Alert className="border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
            <TriangleAlert className="size-4" />
            <AlertTitle>El saldo de las facturas cambió</AlertTitle>
            <AlertDescription className="text-amber-800 dark:text-amber-300">
              Actualizamos los saldos pendientes de este proveedor. Revisá los
              importes imputados antes de volver a confirmar.
            </AlertDescription>
          </Alert>
        )}

        <div className="flex flex-col gap-6">
          <Card className="border bg-card shadow-xs">
            <CardHeader className="pb-3">
              <CardTitle className="text-base font-semibold">
                Datos de la orden de pago
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="min-w-0 space-y-1.5 lg:col-span-2">
                  <Label htmlFor="supplier_id">
                    Proveedor <span className="text-destructive">*</span>
                  </Label>
                  <Select
                    value={data.supplier_id}
                    onValueChange={handleSupplierChange}
                  >
                    <SelectTrigger id="supplier_id">
                      <SelectValue placeholder="Seleccionar proveedor..." />
                    </SelectTrigger>
                    <SelectContent>
                      {suppliers.map((supplier) => (
                        <SelectItem
                          key={supplier.id}
                          value={String(supplier.id)}
                        >
                          {supplier.business_name} ({supplier.tax_id})
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <InputError message={errors.supplier_id} />
                </div>

                <div className="min-w-0 space-y-1.5">
                  <Label htmlFor="payment_method_id">
                    Medio de pago <span className="text-destructive">*</span>
                  </Label>
                  <Select
                    value={data.payment_method_id}
                    onValueChange={(value) =>
                      setData('payment_method_id', value)
                    }
                  >
                    <SelectTrigger id="payment_method_id">
                      <SelectValue placeholder="Seleccionar medio..." />
                    </SelectTrigger>
                    <SelectContent>
                      {paymentMethods.map((method) => (
                        <SelectItem key={method.id} value={String(method.id)}>
                          {method.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <InputError message={errors.payment_method_id} />
                </div>

                <div className="min-w-0 space-y-1.5">
                  <Label htmlFor="date">
                    Fecha <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    id="date"
                    type="date"
                    value={data.date}
                    onChange={(event) => setData('date', event.target.value)}
                  />
                  <InputError message={errors.date} />
                </div>

                <div className="min-w-0 space-y-1.5 sm:col-span-2 lg:col-span-4">
                  <Label htmlFor="notes">Observaciones</Label>
                  <Textarea
                    id="notes"
                    placeholder="Información adicional de la orden (opcional)."
                    value={data.notes}
                    onChange={(event) => setData('notes', event.target.value)}
                    rows={2}
                  />
                  <InputError message={errors.notes} />
                </div>
              </div>
            </CardContent>
          </Card>

          <Card className="border bg-card shadow-xs">
            <CardHeader className="border-b pb-3">
              <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <CardTitle className="text-base font-semibold">
                    Facturas a pagar
                  </CardTitle>
                  <p className="mt-0.5 text-xs text-muted-foreground">
                    Tildá las facturas e ingresá el importe a imputar a cada
                    una. No se puede imputar más que el saldo pendiente.
                  </p>
                </div>
                <div className="text-right">
                  <span className="text-xs text-muted-foreground">
                    Total de la orden:
                  </span>
                  <div className="font-mono text-xl font-bold text-primary">
                    {formatCurrency(totalCents / 100)}
                  </div>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-4 pt-4">
              <InvoiceSelectionTable
                rows={rows}
                hasSupplier={data.supplier_id !== ''}
                loading={loadingInvoices}
                onToggle={toggleInvoice}
                onAmountChange={changeAmount}
                sanitizeAmount={sanitizeDecimal}
              />
              <InputError message={errors.items} />

              <div className="flex flex-col gap-3 rounded-lg border bg-muted/40 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="text-xs text-muted-foreground">
                  {data.items.length === 0
                    ? 'Seleccioná al menos una factura para emitir la orden.'
                    : `${data.items.length} factura${data.items.length === 1 ? '' : 's'} seleccionada${data.items.length === 1 ? '' : 's'}` +
                      (selectedPaymentMethod
                        ? ` · ${selectedPaymentMethod.name}`
                        : '')}
                </div>
                <Button
                  type="button"
                  onClick={submit}
                  disabled={!canSubmit}
                  className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700"
                >
                  {processing && <Loader2 className="size-4 animate-spin" />}
                  {processing ? (
                    'Emitiendo...'
                  ) : (
                    <>
                      <Wallet className="size-4" />
                      Emitir orden de pago
                    </>
                  )}
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  );
}

CreatePaymentOrder.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Compras', href: '#' },
    { title: 'Órdenes de Pago', href: '#' },
    { title: 'Nueva', href: '#' },
  ] satisfies BreadcrumbItem[],
};
