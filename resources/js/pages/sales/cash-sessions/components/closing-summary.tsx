import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { cn, formatCurrency } from '@/lib/utils';

type Session = App.Data.Sales.CashSessionData;

/**
 * Money strings to integer cents, so differences are compared exactly.
 */
export function toCents(amount: number | string): number {
  return Math.round(Number(amount) * 100);
}

/**
 * Declared minus expected (HU-060): zero is balanced, positive a surplus and
 * negative a shortage.
 */
export function Difference({
  cents,
  className,
}: {
  cents: number;
  className?: string;
}) {
  if (cents === 0) {
    return (
      <span
        className={cn(
          'font-medium text-emerald-700 dark:text-emerald-400',
          className,
        )}
      >
        Cuadrado
      </span>
    );
  }

  return (
    <span
      className={cn(
        'font-semibold tabular-nums',
        cents > 0
          ? 'text-amber-700 dark:text-amber-400'
          : 'text-rose-700 dark:text-rose-400',
        className,
      )}
    >
      {cents > 0 ? 'Sobrante ' : 'Faltante '}
      {formatCurrency(Math.abs(cents) / 100)}
    </span>
  );
}

/**
 * The cash closing summary of a closed session (HU-060): opening, sales,
 * incomes, expenses, and expected, declared and difference per payment method.
 */
export function ClosingSummary({ cashSession }: { cashSession: Session }) {
  const totalsByMethod = new Map(
    cashSession.expected_totals.map((total) => [
      total.payment_method_id,
      total,
    ]),
  );
  const sum = (amounts: string[]) =>
    amounts.reduce((cents, amount) => cents + toCents(amount), 0);
  const lines = cashSession.closure_lines;
  const lineTotals = lines.map((line) =>
    totalsByMethod.get(line.payment_method_id),
  );
  const countedBills = cashSession.closing_counts.filter(
    (count) => count.quantity > 0,
  );

  return (
    <Card>
      <CardHeader className="space-y-1">
        <CardTitle className="text-lg">
          Rendición del turno #{cashSession.id}
        </CardTitle>
        <dl className="grid grid-cols-1 gap-x-6 gap-y-1 text-sm text-muted-foreground sm:grid-cols-2">
          <div>
            <dt className="inline">Caja: </dt>
            <dd className="inline font-medium text-foreground">
              {cashSession.point_of_sale_number} · {cashSession.branch_name}
            </dd>
          </div>
          <div>
            <dt className="inline">Cajero: </dt>
            <dd className="inline font-medium text-foreground">
              {cashSession.user_name}
            </dd>
          </div>
          <div>
            <dt className="inline">Apertura: </dt>
            <dd className="inline font-medium text-foreground">
              {cashSession.opened_at_formatted}
            </dd>
          </div>
          <div>
            <dt className="inline">Cierre: </dt>
            <dd className="inline font-medium text-foreground">
              {cashSession.closed_at_formatted}
            </dd>
          </div>
        </dl>
      </CardHeader>

      <CardContent className="space-y-6">
        <div className="overflow-x-auto rounded-md border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Medio de pago</TableHead>
                <TableHead className="text-right">Fondo inicial</TableHead>
                <TableHead className="text-right">Ventas</TableHead>
                <TableHead className="text-right">Ingresos</TableHead>
                <TableHead className="text-right">Egresos</TableHead>
                <TableHead className="text-right">Esperado</TableHead>
                <TableHead className="text-right">Declarado</TableHead>
                <TableHead className="text-right">Diferencia</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {lines.map((line, index) => {
                const total = lineTotals[index];

                return (
                  <TableRow key={line.payment_method_id}>
                    <TableCell>
                      <div className="font-medium">
                        {line.payment_method_name}
                      </div>
                      {line.batch_reference && (
                        <div className="text-xs text-muted-foreground">
                          Lote POSNET {line.batch_reference}
                        </div>
                      )}
                    </TableCell>
                    <TableCell className="text-right tabular-nums">
                      {formatCurrency(total?.opening_amount ?? 0)}
                    </TableCell>
                    <TableCell className="text-right tabular-nums">
                      {formatCurrency(total?.sales_amount ?? 0)}
                    </TableCell>
                    <TableCell className="text-right tabular-nums">
                      {formatCurrency(total?.income_amount ?? 0)}
                    </TableCell>
                    <TableCell className="text-right tabular-nums">
                      {formatCurrency(total?.expense_amount ?? 0)}
                    </TableCell>
                    <TableCell className="text-right tabular-nums">
                      {formatCurrency(line.expected_amount)}
                    </TableCell>
                    <TableCell className="text-right tabular-nums">
                      {formatCurrency(line.declared_amount)}
                    </TableCell>
                    <TableCell className="text-right">
                      <Difference cents={toCents(line.difference)} />
                    </TableCell>
                  </TableRow>
                );
              })}
            </TableBody>
            <TableFooter>
              <TableRow>
                <TableCell className="font-semibold">Total</TableCell>
                <TableCell className="text-right font-semibold tabular-nums">
                  {formatCurrency(
                    sum(lineTotals.map((t) => t?.opening_amount ?? '0')) / 100,
                  )}
                </TableCell>
                <TableCell className="text-right font-semibold tabular-nums">
                  {formatCurrency(
                    sum(lineTotals.map((t) => t?.sales_amount ?? '0')) / 100,
                  )}
                </TableCell>
                <TableCell className="text-right font-semibold tabular-nums">
                  {formatCurrency(
                    sum(lineTotals.map((t) => t?.income_amount ?? '0')) / 100,
                  )}
                </TableCell>
                <TableCell className="text-right font-semibold tabular-nums">
                  {formatCurrency(
                    sum(lineTotals.map((t) => t?.expense_amount ?? '0')) / 100,
                  )}
                </TableCell>
                <TableCell className="text-right font-semibold tabular-nums">
                  {formatCurrency(
                    sum(lines.map((line) => line.expected_amount)) / 100,
                  )}
                </TableCell>
                <TableCell className="text-right font-semibold tabular-nums">
                  {formatCurrency(
                    sum(lines.map((line) => line.declared_amount)) / 100,
                  )}
                </TableCell>
                <TableCell className="text-right">
                  <Difference
                    cents={sum(lines.map((line) => line.difference))}
                  />
                </TableCell>
              </TableRow>
            </TableFooter>
          </Table>
        </div>

        <div className="grid gap-6 md:grid-cols-2">
          <div className="space-y-2">
            <h4 className="text-sm font-semibold">Conteo de efectivo</h4>
            {countedBills.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                No se contaron billetes.
              </p>
            ) : (
              <div className="rounded-md border">
                <Table>
                  <TableBody>
                    {countedBills.map((count) => (
                      <TableRow key={count.denomination}>
                        <TableCell>{count.label}</TableCell>
                        <TableCell className="text-right tabular-nums">
                          × {count.quantity}
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                          {formatCurrency(count.subtotal)}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            )}
          </div>

          <div className="space-y-2">
            <h4 className="text-sm font-semibold">Observaciones</h4>
            <p className="text-sm whitespace-pre-line text-muted-foreground">
              {cashSession.closing_notes ?? 'Sin observaciones.'}
            </p>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
