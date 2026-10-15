import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { formatCurrency } from '@/lib/utils';
import DashboardBreakdown from './dashboard-breakdown';
import DashboardKpiCard from './dashboard-kpi-card';
import DashboardSectionPlaceholder from './dashboard-section-placeholder';

type Props = {
  revenues: App.Data.Reporting.DashboardRevenuesData | null;
};

const title = 'Ingresos del período';
const description =
  'Ventas confirmadas de mostrador y online, por canal, sucursal y medio de pago.';

function formatVariation(variation: number | null): string {
  if (variation === null) {
    return 'Sin ventas en el período anterior para comparar.';
  }

  const sign = variation > 0 ? '+' : '';

  return `${sign}${variation.toLocaleString('es-AR', {
    maximumFractionDigits: 1,
  })} % respecto al período anterior.`;
}

export default function DashboardRevenuesSection({ revenues }: Props) {
  if (revenues === null) {
    return (
      <DashboardSectionPlaceholder
        title={title}
        description={description}
        story="EPIC-18"
      />
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        <CardDescription>{description}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-6">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <DashboardKpiCard
            label="Total facturado"
            value={formatCurrency(revenues.total_invoiced)}
            hint={formatVariation(revenues.total_invoiced_variation_pct)}
          />
          <DashboardKpiCard
            label="Ingreso neto sin IVA"
            value={formatCurrency(revenues.net_revenue)}
            hint={`IVA: ${formatCurrency(revenues.vat_total)}`}
          />
          <DashboardKpiCard
            label="Operaciones confirmadas"
            value={String(revenues.confirmed_sales_count)}
          />
          <DashboardKpiCard
            label="Ticket promedio"
            value={formatCurrency(revenues.average_ticket)}
          />
        </div>

        <div className="grid gap-4 xl:grid-cols-3">
          <DashboardBreakdown
            title="Canal de venta"
            items={revenues.by_channel}
          />
          <DashboardBreakdown title="Sucursal" items={revenues.by_branch} />
          <DashboardBreakdown
            title="Medio de pago"
            items={revenues.by_payment_method}
            emptyMessage="Sin cobros en el período."
          />
        </div>
      </CardContent>
    </Card>
  );
}
