import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Calculator, Loader2, ReceiptText } from 'lucide-react';
import type { FormEvent } from 'react';
import { useMemo } from 'react';
import { toast } from 'sonner';
import { store } from '@/actions/App/Http/Controllers/Purchasing/SupplierVoucherController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
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
import { dashboard } from '@/routes';
import { index } from '@/routes/purchasing/vouchers';
import type { BreadcrumbItem } from '@/types';

type Supplier = App.Data.Purchasing.SupplierOptionData;
type Option = App.Data.Purchasing.SupplierVoucherOptionData;

type Props = {
  suppliers: Supplier[];
  voucherTypes: Option[];
  letters: Option[];
  today: string;
};

type VoucherFormData = {
  supplier_id: string;
  type: string;
  letter: string;
  point_of_sale: string;
  number: string;
  issue_date: string;
  due_date: string;
  net_amount: string;
  other_taxes_amount: string;
  notes: string;
};

function decimalToCents(value: string): number | null {
  const normalized = value.trim().replace(',', '.');

  if (!/^\d+(?:\.\d{1,2})?$/.test(normalized)) {
    return null;
  }

  const [whole, decimals = ''] = normalized.split('.');

  return Number(whole) * 100 + Number(decimals.padEnd(2, '0'));
}

function normalizeAmount(value: string): string {
  const cents = decimalToCents(value);

  return cents === null ? value : (cents / 100).toFixed(2);
}

function formatAmountForDisplay(value: string): string {
  const [whole = '', decimals] = value.split('.', 2);
  const digits = whole.replace(/\D/g, '').replace(/^0+(?=\d)/, '') || '0';
  const groupedWhole = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

  return decimals === undefined
    ? groupedWhole
    : `${groupedWhole},${decimals.slice(0, 2)}`;
}

function parseDisplayedAmount(value: string): string {
  const sanitized = value.replace(/[^\d,]/g, '');
  const hasDecimalSeparator = sanitized.includes(',');
  const [whole = '', decimals = ''] = sanitized.split(',', 2);
  const normalizedWhole = whole.replace(/^0+(?=\d)/, '') || '0';

  return hasDecimalSeparator
    ? `${normalizedWhole}.${decimals.slice(0, 2)}`
    : normalizedWhole;
}

function onlyDigits(value: string, maximumLength: number): string {
  return value.replace(/\D/g, '').slice(0, maximumLength);
}

export default function CreateSupplierVoucher({
  suppliers,
  voucherTypes,
  letters,
  today,
}: Props) {
  const form = useForm<VoucherFormData>({
    supplier_id: '',
    type: 'factura',
    letter: 'A',
    point_of_sale: '',
    number: '',
    issue_date: today,
    due_date: '',
    net_amount: '0.00',
    other_taxes_amount: '0.00',
    notes: '',
  });

  const netCents = useMemo(
    () => decimalToCents(form.data.net_amount),
    [form.data.net_amount],
  );
  const otherTaxesCents = useMemo(
    () => decimalToCents(form.data.other_taxes_amount),
    [form.data.other_taxes_amount],
  );
  const discriminatesVat = form.data.letter === 'A' || form.data.letter === 'M';
  // Mirrors the backend's integer arithmetic: intdiv((netCents * 21) + 50, 100).
  const vatCents =
    netCents === null
      ? null
      : discriminatesVat
        ? Math.floor((netCents * 21 + 50) / 100)
        : 0;
  const totalCents =
    netCents === null || vatCents === null || otherTaxesCents === null
      ? null
      : netCents + vatCents + otherTaxesCents;

  const formattedDerivedAmount = (cents: number | null) =>
    cents === null ? '' : formatAmountForDisplay((cents / 100).toFixed(2));

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    form.submit(store(), {
      onSuccess: () =>
        toast.success('Comprobante de proveedor registrado correctamente.'),
      onError: () => toast.error('Revisá los datos del comprobante ingresado.'),
    });
  };

  const padFiscalNumber = (
    field: 'point_of_sale' | 'number',
    length: number,
  ) => {
    const value = form.data[field];

    if (value !== '') {
      form.setData(field, value.padStart(length, '0'));
    }
  };

  return (
    <>
      <Head title="Registrar comprobante de proveedor" />

      <form
        onSubmit={submit}
        className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8"
      >
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <Heading
            title="Registrar comprobante"
            description="Ingresá la factura o nota emitida por un proveedor activo. El estado y el saldo se calculan automáticamente."
          />
          <Button variant="outline" asChild>
            <Link href={index()}>
              <ArrowLeft className="size-4" />
              Volver al listado
            </Link>
          </Button>
        </div>

        {suppliers.length === 0 && (
          <Alert className="border-warning-fg/30 bg-warning-bg text-warning-fg">
            <ReceiptText />
            <AlertTitle>No hay proveedores activos</AlertTitle>
            <AlertDescription className="text-warning-fg">
              Activá o registrá un proveedor antes de cargar un comprobante.
            </AlertDescription>
          </Alert>
        )}

        <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(320px,0.45fr)]">
          <div className="flex flex-col gap-6">
            <Card>
              <CardHeader>
                <CardTitle>Identificación fiscal</CardTitle>
                <CardDescription>
                  La combinación de estos datos identifica al comprobante y no
                  podrá repetirse.
                </CardDescription>
              </CardHeader>
              <CardContent className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2 sm:col-span-2">
                  <Label htmlFor="supplier_id">Proveedor *</Label>
                  <Select
                    value={form.data.supplier_id}
                    onValueChange={(value) =>
                      form.setData('supplier_id', value)
                    }
                  >
                    <SelectTrigger id="supplier_id">
                      <SelectValue placeholder="Seleccioná un proveedor activo" />
                    </SelectTrigger>
                    <SelectContent>
                      {suppliers.map((supplier) => (
                        <SelectItem
                          key={supplier.id}
                          value={String(supplier.id)}
                        >
                          {supplier.business_name} · {supplier.tax_id}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <InputError message={form.errors.supplier_id} />
                </div>

                <div className="grid gap-2">
                  <Label htmlFor="type">Tipo *</Label>
                  <Select
                    value={form.data.type}
                    onValueChange={(value) => form.setData('type', value)}
                  >
                    <SelectTrigger id="type">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {voucherTypes.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                          {option.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <InputError message={form.errors.type} />
                </div>

                <div className="grid gap-2">
                  <Label htmlFor="letter">Letra *</Label>
                  <Select
                    value={form.data.letter}
                    onValueChange={(value) => form.setData('letter', value)}
                  >
                    <SelectTrigger id="letter">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {letters.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                          {option.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <InputError message={form.errors.letter} />
                </div>

                <div className="grid gap-2">
                  <Label htmlFor="point_of_sale">Punto de venta *</Label>
                  <Input
                    id="point_of_sale"
                    value={form.data.point_of_sale}
                    onChange={(event) =>
                      form.setData(
                        'point_of_sale',
                        onlyDigits(event.target.value, 4),
                      )
                    }
                    onBlur={() => padFiscalNumber('point_of_sale', 4)}
                    inputMode="numeric"
                    maxLength={4}
                    placeholder="0001"
                    required
                  />
                  <p className="text-xs text-muted-foreground">
                    Son los primeros 4 dígitos del número fiscal impreso por el
                    proveedor, antes del guion. Podés ingresar menos dígitos; se
                    completará con ceros.
                  </p>
                  <InputError message={form.errors.point_of_sale} />
                </div>

                <div className="grid gap-2">
                  <Label htmlFor="number">Número *</Label>
                  <Input
                    id="number"
                    value={form.data.number}
                    onChange={(event) =>
                      form.setData('number', onlyDigits(event.target.value, 8))
                    }
                    onBlur={() => padFiscalNumber('number', 8)}
                    inputMode="numeric"
                    maxLength={8}
                    placeholder="00000001"
                    required
                  />
                  <p className="text-xs text-muted-foreground">
                    Son los 8 dígitos correlativos posteriores al guion que
                    identifican el comprobante dentro de ese punto de venta.
                  </p>
                  <InputError message={form.errors.number} />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Fechas</CardTitle>
                <CardDescription>
                  La emisión no puede ser futura y el vencimiento es opcional.
                </CardDescription>
              </CardHeader>
              <CardContent className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                  <Label htmlFor="issue_date">Fecha de emisión *</Label>
                  <Input
                    id="issue_date"
                    type="date"
                    max={today}
                    value={form.data.issue_date}
                    onChange={(event) =>
                      form.setData('issue_date', event.target.value)
                    }
                    required
                  />
                  <InputError message={form.errors.issue_date} />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="due_date">Fecha de vencimiento</Label>
                  <Input
                    id="due_date"
                    type="date"
                    min={form.data.issue_date}
                    value={form.data.due_date}
                    onChange={(event) =>
                      form.setData('due_date', event.target.value)
                    }
                  />
                  <InputError message={form.errors.due_date} />
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Importes</CardTitle>
                <CardDescription>
                  El sistema calcula el IVA y el total; no se ingresan
                  manualmente.
                </CardDescription>
              </CardHeader>
              <CardContent className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                  <Label htmlFor="net_amount">Importe neto gravado *</Label>
                  <Input
                    id="net_amount"
                    value={formatAmountForDisplay(form.data.net_amount)}
                    onChange={(event) =>
                      form.setData(
                        'net_amount',
                        parseDisplayedAmount(event.target.value),
                      )
                    }
                    onBlur={() =>
                      form.setData(
                        'net_amount',
                        normalizeAmount(form.data.net_amount),
                      )
                    }
                    inputMode="decimal"
                    placeholder="0,00"
                    required
                  />
                  <p className="text-xs text-muted-foreground">
                    {discriminatesVat
                      ? 'Base usada para calcular automáticamente el IVA del 21%.'
                      : form.data.letter === 'B'
                        ? 'Para letra B, ingresá el importe con IVA incluido; no se discrimina.'
                        : 'Para letra C, ingresá el importe de la operación sin IVA.'}
                  </p>
                  <p className="text-xs text-muted-foreground">
                    Los miles se separan automáticamente con puntos. Usá coma
                    para ingresar centavos.
                  </p>
                  <InputError message={form.errors.net_amount} />
                </div>

                <div className="grid gap-2">
                  <Label htmlFor="vat_amount">IVA</Label>
                  <Input
                    id="vat_amount"
                    value={formattedDerivedAmount(vatCents)}
                    readOnly
                    aria-readonly="true"
                    className="bg-muted tabular-nums"
                  />
                  <p className="text-xs text-muted-foreground">
                    {discriminatesVat
                      ? 'Calculado automáticamente al 21%.'
                      : 'No se discrimina ni calcula IVA para esta letra.'}
                  </p>
                </div>

                <div className="grid gap-2">
                  <Label htmlFor="other_taxes_amount">
                    Otros tributos / percepciones
                  </Label>
                  <Input
                    id="other_taxes_amount"
                    value={formatAmountForDisplay(form.data.other_taxes_amount)}
                    onChange={(event) =>
                      form.setData(
                        'other_taxes_amount',
                        parseDisplayedAmount(event.target.value),
                      )
                    }
                    onBlur={() =>
                      form.setData(
                        'other_taxes_amount',
                        normalizeAmount(form.data.other_taxes_amount),
                      )
                    }
                    inputMode="decimal"
                    placeholder="0,00"
                  />
                  <InputError message={form.errors.other_taxes_amount} />
                </div>

                <div className="grid gap-2">
                  <Label htmlFor="total_amount">Importe total</Label>
                  <Input
                    id="total_amount"
                    value={formattedDerivedAmount(totalCents)}
                    readOnly
                    aria-readonly="true"
                    className="bg-muted font-semibold tabular-nums"
                  />
                  <p className="text-xs text-muted-foreground">
                    Neto + IVA + otros tributos.
                  </p>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Observaciones</CardTitle>
                <CardDescription>
                  Información adicional opcional.
                </CardDescription>
              </CardHeader>
              <CardContent className="grid gap-2">
                <Label htmlFor="notes">Notas</Label>
                <Textarea
                  id="notes"
                  value={form.data.notes}
                  onChange={(event) =>
                    form.setData('notes', event.target.value)
                  }
                  rows={4}
                  maxLength={2000}
                />
                <InputError message={form.errors.notes} />
              </CardContent>
            </Card>
          </div>

          <div className="flex flex-col gap-4 xl:sticky xl:top-6 xl:self-start">
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Calculator className="size-5" />
                  Control del total
                </CardTitle>
              </CardHeader>
              <CardContent className="flex flex-col gap-4">
                <div className="flex items-center justify-between text-sm">
                  <span className="text-muted-foreground">Suma calculada</span>
                  <span className="font-semibold tabular-nums">
                    {totalCents === null
                      ? '—'
                      : `$ ${(totalCents / 100).toLocaleString('es-AR', {
                          minimumFractionDigits: 2,
                          maximumFractionDigits: 2,
                        })}`}
                  </span>
                </div>
                {totalCents !== null && totalCents <= 0 && (
                  <Alert className="border-error-fg/30 bg-error-bg text-error-fg">
                    <AlertTitle>El total debe ser mayor a cero</AlertTitle>
                    <AlertDescription className="text-error-fg">
                      Ingresá al menos un importe positivo.
                    </AlertDescription>
                  </Alert>
                )}
                <p className="text-xs text-muted-foreground">
                  Las facturas nacen pendientes con saldo igual al total. Las
                  notas nacen pendientes de imputar.
                </p>
                <Button
                  type="submit"
                  disabled={
                    form.processing ||
                    suppliers.length === 0 ||
                    totalCents === null ||
                    totalCents <= 0
                  }
                  className="w-full"
                >
                  {form.processing && (
                    <Loader2 className="size-4 animate-spin" />
                  )}
                  Registrar comprobante
                </Button>
              </CardContent>
            </Card>
          </div>
        </div>
      </form>
    </>
  );
}

CreateSupplierVoucher.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Comprobantes de proveedores', href: index() },
    { title: 'Registrar comprobante', href: '#' },
  ] satisfies BreadcrumbItem[],
};
