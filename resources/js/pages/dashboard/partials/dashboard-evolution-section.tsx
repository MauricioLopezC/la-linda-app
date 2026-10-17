import {
  Bar,
  CartesianGrid,
  ComposedChart,
  Line,
  ReferenceLine,
  XAxis,
  YAxis,
} from 'recharts';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import type { ChartConfig } from '@/components/ui/chart';
import {
  ChartContainer,
  ChartLegend,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
} from '@/components/ui/chart';
import { formatCurrency } from '@/lib/utils';
import DashboardKpiCard from './dashboard-kpi-card';
import DashboardSectionPlaceholder from './dashboard-section-placeholder';

type Props = {
  evolution: App.Data.Reporting.DashboardEvolutionData | null;
  filters: App.Data.Reporting.DashboardFiltersData;
};

const title = 'Resultado neto y evolución';
const description =
  'Ingresos netos sin IVA menos egresos devengados, y su evolución en el período.';

const chartConfig = {
  net_revenue: { label: 'Ingresos', color: 'var(--chart-1)' },
  credit_notes: { label: 'Notas de crédito', color: 'var(--chart-4)' },
  accrued_charges: {
    label: 'Facturas y notas de débito',
    color: 'var(--chart-2)',
  },
  cumulative_net_result: {
    label: 'Resultado acumulado',
    color: 'var(--chart-3)',
  },
} satisfies ChartConfig;

type ChartSeriesKey = keyof typeof chartConfig;

function TooltipRow({ label, value }: { label: string; value: number }) {
  return (
    <div className="flex w-full justify-between gap-4">
      <span className="text-muted-foreground">{label}</span>
      <span className="font-mono font-medium tabular-nums">
        {formatCurrency(value)}
      </span>
    </div>
  );
}

function formatPercentage(value: number): string {
  return `${value.toLocaleString('es-AR', { maximumFractionDigits: 1 })} %`;
}

function formatVariation(variation: number | null): string {
  if (variation === null) {
    return 'Sin resultado en el período anterior para comparar.';
  }

  const sign = variation > 0 ? '+' : '';

  return `${sign}${formatPercentage(variation)} respecto al período anterior.`;
}

/**
 * Daily periods come as `Y-m-d` and monthly ones as `Y-m`.
 */
function formatPeriod(period: string): string {
  const [year, month, day] = period.split('-').map(Number);

  if (day === undefined) {
    return new Date(year, month - 1, 1).toLocaleDateString('es-AR', {
      month: 'short',
      year: 'numeric',
    });
  }

  return new Date(year, month - 1, day).toLocaleDateString('es-AR', {
    day: '2-digit',
    month: '2-digit',
  });
}

export default function DashboardEvolutionSection({
  evolution,
  filters,
}: Props) {
  if (evolution === null) {
    return (
      <DashboardSectionPlaceholder
        title={title}
        description={description}
        story="EPIC-20"
      />
    );
  }

  const netResult = Number(evolution.net_result);
  const isSurplus = netResult >= 0;
  const chartData = evolution.series.map((point) => ({
    period: formatPeriod(point.period),
    net_revenue: Number(point.net_revenue),
    credit_notes: Number(point.credit_notes),
    accrued_charges: -Number(point.accrued_charges),
    net_result: Number(point.net_result),
    cumulative_net_result: Number(point.cumulative_net_result),
  }));
  const isMonthly = evolution.series[0]?.period.length === 7;

  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        <CardDescription>{description}</CardDescription>
      </CardHeader>
      <CardContent className="flex flex-col gap-6">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          <DashboardKpiCard
            label={
              isSurplus
                ? 'Resultado neto — superávit'
                : 'Resultado neto — déficit'
            }
            value={formatCurrency(evolution.net_result)}
            valueClassName={isSurplus ? 'text-primary' : 'text-destructive'}
            hint={formatVariation(evolution.net_result_variation_pct)}
          />
          <DashboardKpiCard
            label="Margen neto"
            value={
              evolution.net_margin_pct === null
                ? '—'
                : formatPercentage(evolution.net_margin_pct)
            }
            hint="Resultado neto sobre los ingresos netos sin IVA."
          />
        </div>

        <div className="space-y-1 text-sm text-muted-foreground">
          <p>
            El resultado neto resta los egresos devengados del bloque de egresos
            a los ingresos netos sin IVA del bloque de ingresos.
          </p>
          <p>
            En el gráfico, lo que suma al resultado va hacia arriba (ventas y
            notas de crédito de proveedores) y lo que resta va hacia abajo
            (facturas y notas de débito). La línea acumula el resultado desde el
            inicio del período y termina en el resultado neto.
          </p>
          {(filters.branch_id !== null || filters.channel !== null) && (
            <p>
              Los filtros de sucursal y canal solo achican los ingresos: las
              compras a proveedores son globales, así que los egresos devengados
              no cambian con esos filtros.
            </p>
          )}
        </div>

        <section aria-labelledby="evolution-chart-title" className="space-y-4">
          <h3 id="evolution-chart-title" className="font-medium">
            Ingresos vs. egresos por {isMonthly ? 'mes' : 'día'}
          </h3>
          <ChartContainer
            config={chartConfig}
            className="aspect-auto h-80 w-full"
          >
            <ComposedChart
              accessibilityLayer
              data={chartData}
              stackOffset="sign"
              margin={{ left: 8, right: 16 }}
            >
              <CartesianGrid vertical={false} />
              <XAxis
                dataKey="period"
                tickLine={false}
                axisLine={false}
                minTickGap={16}
              />
              <YAxis
                width={110}
                tickLine={false}
                axisLine={false}
                tickFormatter={(value: number) => formatCurrency(value)}
              />
              <ChartTooltip
                content={
                  <ChartTooltipContent
                    formatter={(value, name, item) => {
                      const key = name as ChartSeriesKey;

                      if (key === 'credit_notes' && Number(value) === 0) {
                        return null;
                      }

                      if (key === 'cumulative_net_result') {
                        return (
                          <div className="grid w-full gap-1.5 border-t pt-1.5">
                            <TooltipRow
                              label={`Saldo del ${isMonthly ? 'mes' : 'día'}`}
                              value={Number(item.payload?.net_result)}
                            />
                            <TooltipRow
                              label={chartConfig[key].label}
                              value={Number(value)}
                            />
                          </div>
                        );
                      }

                      return (
                        <TooltipRow
                          label={chartConfig[key]?.label ?? name}
                          value={Number(value)}
                        />
                      );
                    }}
                  />
                }
              />
              <ChartLegend content={<ChartLegendContent />} />
              <ReferenceLine y={0} stroke="var(--border)" />
              <Bar
                dataKey="net_revenue"
                stackId="flow"
                fill="var(--color-net_revenue)"
              />
              <Bar
                dataKey="credit_notes"
                stackId="flow"
                fill="var(--color-credit_notes)"
              />
              <Bar
                dataKey="accrued_charges"
                stackId="flow"
                fill="var(--color-accrued_charges)"
              />
              <Line
                dataKey="cumulative_net_result"
                type="linear"
                stroke="var(--color-cumulative_net_result)"
                strokeWidth={2}
                dot={{ r: 3, fill: 'var(--color-cumulative_net_result)' }}
              />
            </ComposedChart>
          </ChartContainer>
        </section>
      </CardContent>
    </Card>
  );
}
