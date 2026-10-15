import { Bar, BarChart, XAxis, YAxis } from 'recharts';
import type { ChartConfig } from '@/components/ui/chart';
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
} from '@/components/ui/chart';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { formatCurrency } from '@/lib/utils';

type Props = {
  title: string;
  items: App.Data.Reporting.DashboardBreakdownItemData[];
  emptyMessage?: string;
};

const chartConfig = {
  amount: { label: 'Importe' },
} satisfies ChartConfig;

const chartColorCount = 5;

function formatShare(amount: string, total: number): string {
  if (total === 0) {
    return '0 %';
  }

  return `${((Number(amount) / total) * 100).toLocaleString('es-AR', {
    maximumFractionDigits: 1,
  })} %`;
}

/**
 * A dashboard breakdown (by channel, branch or payment method) as a bar chart of each row's
 * share plus the table with its amount, operations and percentage.
 */
export default function DashboardBreakdown({
  title,
  items,
  emptyMessage = 'Sin movimientos en el período.',
}: Props) {
  const total = items.reduce((sum, item) => sum + Number(item.amount), 0);
  const chartData = items.map((item, position) => ({
    label: item.label,
    amount: Number(item.amount),
    fill: `var(--chart-${(position % chartColorCount) + 1})`,
  }));

  return (
    <div className="space-y-3 rounded-lg border border-sidebar-border/70 p-4">
      <h3 className="text-sm font-semibold text-foreground">{title}</h3>

      {items.length === 0 ? (
        <p className="text-sm text-muted-foreground">{emptyMessage}</p>
      ) : (
        <>
          <ChartContainer
            config={chartConfig}
            className="aspect-auto w-full"
            style={{ height: Math.max(items.length * 44, 88) }}
          >
            <BarChart
              accessibilityLayer
              data={chartData}
              layout="vertical"
              margin={{ left: 0, right: 8 }}
            >
              <YAxis
                dataKey="label"
                type="category"
                tickLine={false}
                axisLine={false}
                width={110}
              />
              <XAxis dataKey="amount" type="number" hide />
              <ChartTooltip
                cursor={false}
                content={
                  <ChartTooltipContent
                    hideLabel
                    formatter={(value, _name, entry) => (
                      <div className="flex w-full justify-between gap-4">
                        <span className="text-muted-foreground">
                          {entry.payload.label}
                        </span>
                        <span className="font-mono font-medium text-foreground tabular-nums">
                          {formatCurrency(Number(value))}
                        </span>
                      </div>
                    )}
                  />
                }
              />
              <Bar dataKey="amount" radius={4} />
            </BarChart>
          </ChartContainer>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{title}</TableHead>
                <TableHead className="text-right">Operaciones</TableHead>
                <TableHead className="text-right">Importe</TableHead>
                <TableHead className="text-right">%</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {items.map((item) => (
                <TableRow key={item.key}>
                  <TableCell>{item.label}</TableCell>
                  <TableCell className="text-right tabular-nums">
                    {item.count}
                  </TableCell>
                  <TableCell className="text-right tabular-nums">
                    {formatCurrency(item.amount)}
                  </TableCell>
                  <TableCell className="text-right text-muted-foreground tabular-nums">
                    {formatShare(item.amount, total)}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </>
      )}
    </div>
  );
}
