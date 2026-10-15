import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { formatCurrency } from '@/lib/utils';
import DashboardKpiCard from './dashboard-kpi-card';
import DashboardSectionPlaceholder from './dashboard-section-placeholder';

type Props = {
  revenues: App.Data.Reporting.DashboardRevenuesData | null;
};

const title = 'Ingresos del período';
const description =
  'Ventas confirmadas de mostrador y online, por canal, sucursal y medio de pago.';

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
      <CardContent className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <DashboardKpiCard
          label="Total facturado"
          value={formatCurrency(revenues.total_invoiced)}
        />
        <DashboardKpiCard
          label="Ingreso neto sin IVA"
          value={formatCurrency(revenues.net_revenue)}
        />
        <DashboardKpiCard
          label="Operaciones confirmadas"
          value={String(revenues.confirmed_sales_count)}
        />
        <DashboardKpiCard
          label="Ticket promedio"
          value={formatCurrency(revenues.average_ticket)}
        />
      </CardContent>
    </Card>
  );
}
