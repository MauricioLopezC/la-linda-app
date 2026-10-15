import { Link } from '@inertiajs/react';
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
} from '@/components/ui/chart';
import { formatCurrency } from '@/lib/utils';
import { index as accountStatement } from '@/routes/purchasing/account-statement';
import { index as paymentOrders } from '@/routes/purchasing/payment-orders';
import DashboardKpiCard from './dashboard-kpi-card';
import DashboardSectionPlaceholder from './dashboard-section-placeholder';

type Props = {
  expenses: App.Data.Reporting.DashboardExpensesData | null;
  filters: App.Data.Reporting.DashboardFiltersData;
};

const title = 'Egresos del período';
const description =
  'Gasto devengado con proveedores frente a los pagos y egresos de caja efectivos.';

export default function DashboardExpensesSection({ expenses, filters }: Props) {
  if (expenses === null) {
    return (
      <DashboardSectionPlaceholder
        title={title}
        description={description}
        story="EPIC-19"
      />
    );
  }

  const period = { date_from: filters.date_from, date_to: filters.date_to };
  const chartData = expenses.by_payment_method.map((method) => ({
    ...method,
    value: Number(method.amount),
  }));

  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        <CardDescription>{description}</CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-6">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <DashboardKpiCard
            label="Comprobantes recibidos — devengado"
            value={formatCurrency(expenses.accrued_total)}
          />
          <DashboardKpiCard
            label="Pagado a proveedores"
            value={formatCurrency(expenses.paid_to_suppliers_total)}
          />
          <DashboardKpiCard
            label="Egresos operativos de caja"
            value={formatCurrency(expenses.cash_expenses_total)}
          />
          <DashboardKpiCard
            label="Desembolso total"
            value={formatCurrency(expenses.disbursed_total)}
          />
        </div>

        <div className="space-y-1 text-sm text-muted-foreground">
          <p>
            El devengado suma facturas y notas de débito, y resta notas de
            crédito. Los pagos representan órdenes de pago emitidas.
          </p>
          <p>
            Compras y pagos a proveedores son globales. La sucursal solo filtra
            los egresos de caja. El canal no aplica a este bloque.
          </p>
          {filters.branch_id !== null && (
            <p>
              El desembolso total combina pagos globales a proveedores y egresos
              de caja de la sucursal seleccionada.
            </p>
          )}
        </div>

        <section
          aria-labelledby="supplier-payment-methods-title"
          className="space-y-4"
        >
          <h3 id="supplier-payment-methods-title" className="font-medium">
            Pagos a proveedores por medio de pago
          </h3>
          {chartData.length === 0 ? (
            <p className="text-sm text-muted-foreground">
              No hay pagos a proveedores en el período seleccionado.
            </p>
          ) : (
            <>
              <ChartContainer
                config={{ value: { label: 'Pagado', color: 'var(--primary)' } }}
                className="aspect-auto w-full"
                style={{ height: Math.max(180, chartData.length * 48) }}
              >
                <BarChart
                  accessibilityLayer
                  data={chartData}
                  layout="vertical"
                  margin={{ left: 8, right: 16 }}
                >
                  <CartesianGrid horizontal={false} />
                  <XAxis
                    type="number"
                    tickFormatter={(value: number) => formatCurrency(value)}
                  />
                  <YAxis
                    type="category"
                    dataKey="label"
                    width={110}
                    tickLine={false}
                    axisLine={false}
                  />
                  <ChartTooltip
                    content={
                      <ChartTooltipContent
                        formatter={(value) => formatCurrency(Number(value))}
                      />
                    }
                  />
                  <Bar dataKey="value" fill="var(--color-value)" radius={4} />
                </BarChart>
              </ChartContainer>
              <dl className="grid gap-2 text-sm sm:grid-cols-2">
                {expenses.by_payment_method.map((method) => (
                  <div
                    key={method.key}
                    className="flex flex-wrap justify-between gap-2 rounded-md border p-3"
                  >
                    <dt className="break-words">{method.label}</dt>
                    <dd className="font-medium tabular-nums">
                      {formatCurrency(method.amount)}
                    </dd>
                  </div>
                ))}
              </dl>
            </>
          )}
        </section>

        <div className="flex flex-wrap gap-2">
          <Button asChild variant="outline">
            <Link
              href={paymentOrders({ query: { ...period, status: 'emitida' } })}
            >
              Ver pagos y egresos
            </Link>
          </Button>
          <Button asChild variant="outline">
            <Link href={accountStatement({ query: period })}>
              Ver cuentas corrientes
            </Link>
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
