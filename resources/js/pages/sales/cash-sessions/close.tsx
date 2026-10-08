import { Head, Link, setLayoutProps, useForm } from '@inertiajs/react';
import { AlertTriangle, Lock } from 'lucide-react';
import { storeClosing } from '@/actions/App/Http/Controllers/Sales/CashSessionController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Table,
  TableBody,
  TableCell,
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency } from '@/lib/utils';
import { dashboard } from '@/routes';
import { show } from '@/routes/sales/cash-sessions';
import { create as closingCreate } from '@/routes/sales/cash-sessions/closing';
import { index as salesIndex } from '@/routes/sales/sales';
import type { BreadcrumbItem } from '@/types';
import { toCents } from './components/closing-summary';

type Session = App.Data.Sales.CashSessionClosingData;
type Denomination = App.Data.Sales.CashDenominationData;

type Props = {
  cashSession: Session;
  denominations: Denomination[];
  openSalesCount: number;
};

type Declaration = { declared_amount: string; batch_reference: string };

/**
 * A blank or invalid quantity counts as zero for the live total; the backend
 * validates the real input.
 */
function toQuantity(value: string): number {
  const quantity = Number(value);

  return Number.isInteger(quantity) && quantity > 0 ? quantity : 0;
}

export default function CloseCashSession({
  cashSession,
  denominations = [],
  openSalesCount,
}: Props) {
  setLayoutProps({
    breadcrumbs: [
      { title: 'Dashboard', href: dashboard() },
      {
        title: `Turno #${cashSession.id}`,
        href: show.url(cashSession.id),
      },
      {
        title: 'Cerrar caja',
        href: closingCreate.url(cashSession.id),
      },
    ] satisfies BreadcrumbItem[],
  });

  const otherMethods = cashSession.payment_methods.filter(
    (method) => !method.is_cash,
  );

  const form = useForm({
    counts: Object.fromEntries(
      denominations.map((denomination) => [String(denomination.value), '0']),
    ) as Record<string, string>,
    declarations: Object.fromEntries(
      otherMethods.map((method) => [
        String(method.payment_method_id),
        { declared_amount: '', batch_reference: '' },
      ]),
    ) as Record<string, Declaration>,
    closing_notes: '',
  });

  const errors = form.errors as Record<string, string | undefined>;
  const countedCashCents =
    100 *
    denominations.reduce(
      (sum, denomination) =>
        sum +
        denomination.value *
          toQuantity(form.data.counts[String(denomination.value)] ?? ''),
      0,
    );

  const declaredCents = (method: Session['payment_methods'][number]) =>
    method.is_cash
      ? countedCashCents
      : toCents(
          form.data.declarations[String(method.payment_method_id)]
            ?.declared_amount || 0,
        );

  /*
   * The count is blind: the form never knows the expected amounts, so it only
   * learns about a difference when the closing is rejected for missing notes.
   */
  const hasDifference = errors.closing_notes !== undefined;

  const setQuantity = (denomination: number, value: string) => {
    form.setData('counts', {
      ...form.data.counts,
      [String(denomination)]: value,
    });
  };

  const setDeclaration = (
    paymentMethodId: number,
    field: keyof Declaration,
    value: string,
  ) => {
    const key = String(paymentMethodId);

    form.setData('declarations', {
      ...form.data.declarations,
      [key]: { ...form.data.declarations[key], [field]: value },
    });
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    form.transform((data) => ({
      ...data,
      counts: Object.fromEntries(
        Object.entries(data.counts).map(([denomination, quantity]) => [
          denomination,
          quantity === '' ? 0 : Number(quantity),
        ]),
      ),
    }));
    form.post(storeClosing.url(cashSession.id));
  };

  return (
    <>
      <Head title={`Cerrar caja · Turno #${cashSession.id}`} />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <Heading
          title={`Cerrar caja ${cashSession.point_of_sale_number}`}
          description={`Turno #${cashSession.id} · ${cashSession.branch_name} · abierto el ${cashSession.opened_at_formatted}. Contá el efectivo billete por billete y declará lo cobrado con cada otro medio.`}
        />

        {openSalesCount > 0 && (
          <Alert variant="destructive">
            <AlertTriangle />
            <AlertTitle>
              {openSalesCount === 1
                ? 'Hay 1 venta abierta en el turno'
                : `Hay ${openSalesCount} ventas abiertas en el turno`}
            </AlertTitle>
            <AlertDescription>
              <p>
                Cobralas o descartalas antes de cerrar la caja.{' '}
                <Link
                  href={salesIndex.url()}
                  className="font-medium underline underline-offset-2"
                >
                  Ir a ventas
                </Link>
              </p>
            </AlertDescription>
          </Alert>
        )}

        <InputError message={errors.cash_session} />

        <form
          onSubmit={handleSubmit}
          className="grid gap-8 lg:grid-cols-2 lg:items-start"
        >
          <div className="space-y-6">
            <div className="space-y-1.5">
              <Label>Conteo de efectivo</Label>
              <div className="rounded-md border">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Billete</TableHead>
                      <TableHead className="w-32">Cantidad</TableHead>
                      <TableHead className="text-right">Subtotal</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {denominations.map((denomination) => {
                      const key = String(denomination.value);
                      const quantity = form.data.counts[key] ?? '';

                      return (
                        <TableRow key={key}>
                          <TableCell className="font-medium">
                            {denomination.label}
                          </TableCell>
                          <TableCell>
                            <Input
                              type="number"
                              inputMode="numeric"
                              min={0}
                              step={1}
                              aria-label={`Cantidad de billetes de ${denomination.label}`}
                              value={quantity}
                              onChange={(e) =>
                                setQuantity(denomination.value, e.target.value)
                              }
                              onFocus={(e) => e.target.select()}
                            />
                            <InputError message={errors[`counts.${key}`]} />
                          </TableCell>
                          <TableCell className="text-right tabular-nums">
                            {formatCurrency(
                              denomination.value * toQuantity(quantity),
                            )}
                          </TableCell>
                        </TableRow>
                      );
                    })}
                  </TableBody>
                  <TableFooter>
                    <TableRow>
                      <TableCell colSpan={2} className="font-semibold">
                        Efectivo contado
                      </TableCell>
                      <TableCell className="text-right text-base font-semibold tabular-nums">
                        {formatCurrency(countedCashCents / 100)}
                      </TableCell>
                    </TableRow>
                  </TableFooter>
                </Table>
              </div>
              <InputError message={errors.counts} />
            </div>

            {otherMethods.length > 0 && (
              <div className="space-y-3">
                <Label>Otros medios de pago</Label>
                {otherMethods.map((method) => {
                  const key = String(method.payment_method_id);
                  const declaration = form.data.declarations[key];

                  return (
                    <div
                      key={key}
                      className="grid gap-3 rounded-md border p-3 sm:grid-cols-2"
                    >
                      <div className="space-y-1.5">
                        <Label htmlFor={`declared-${key}`}>
                          {method.payment_method_name}{' '}
                          <span className="font-normal text-muted-foreground">
                            ({method.kind_label})
                          </span>
                        </Label>
                        <Input
                          id={`declared-${key}`}
                          type="number"
                          inputMode="decimal"
                          min={0}
                          step="0.01"
                          placeholder="0.00"
                          value={declaration?.declared_amount ?? ''}
                          onChange={(e) =>
                            setDeclaration(
                              method.payment_method_id,
                              'declared_amount',
                              e.target.value,
                            )
                          }
                          required
                        />
                        <InputError
                          message={
                            errors[`declarations.${key}.declared_amount`]
                          }
                        />
                      </div>
                      {method.requires_batch_reference && (
                        <div className="space-y-1.5">
                          <Label htmlFor={`batch-${key}`}>
                            Número de lote del POSNET
                          </Label>
                          <Input
                            id={`batch-${key}`}
                            maxLength={50}
                            value={declaration?.batch_reference ?? ''}
                            onChange={(e) =>
                              setDeclaration(
                                method.payment_method_id,
                                'batch_reference',
                                e.target.value,
                              )
                            }
                            required
                          />
                          <InputError
                            message={
                              errors[`declarations.${key}.batch_reference`]
                            }
                          />
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            )}
          </div>

          <div className="space-y-6 lg:sticky lg:top-4">
            <div className="space-y-1.5">
              <Label>Total declarado</Label>
              <div className="rounded-md border">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Medio de pago</TableHead>
                      <TableHead className="text-right">Declarado</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {cashSession.payment_methods.map((method) => (
                      <TableRow key={method.payment_method_id}>
                        <TableCell className="font-medium">
                          {method.payment_method_name}
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                          {formatCurrency(declaredCents(method) / 100)}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
              <p className="text-xs text-muted-foreground">
                Declará lo que contaste. Al cerrar, el sistema lo compara con
                los movimientos del turno y el resumen muestra la diferencia por
                medio de pago.
              </p>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="closing_notes">
                Observación
                {hasDifference ? ' (obligatoria: el arqueo no cuadra)' : ''}
              </Label>
              <Textarea
                id="closing_notes"
                rows={3}
                maxLength={2000}
                placeholder={
                  hasDifference
                    ? 'Explicá el motivo de la diferencia.'
                    : 'Opcional. Es obligatoria si el arqueo no cuadra.'
                }
                value={form.data.closing_notes}
                onChange={(e) => form.setData('closing_notes', e.target.value)}
                required={hasDifference}
              />
              <InputError message={errors.closing_notes} />
            </div>

            <div className="flex flex-wrap gap-2">
              <Button
                type="submit"
                disabled={
                  form.processing ||
                  openSalesCount > 0 ||
                  (hasDifference && form.data.closing_notes.trim() === '')
                }
              >
                <Lock className="mr-1.5 size-4" />
                {form.processing ? 'Cerrando...' : 'Cerrar caja'}
              </Button>
              <Button variant="ghost" asChild>
                <Link href={show.url(cashSession.id)}>Cancelar</Link>
              </Button>
            </div>
          </div>
        </form>
      </div>
    </>
  );
}
