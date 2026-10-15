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
  expenses: App.Data.Reporting.DashboardExpensesData | null;
};

const title = 'Egresos del período';
const description =
  'Gasto devengado con proveedores frente a los pagos y egresos de caja efectivos.';

export default function DashboardExpensesSection({ expenses }: Props) {
  if (expenses === null) {
    return (
      <DashboardSectionPlaceholder
        title={title}
        description={description}
        story="EPIC-19"
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
          label="Devengado"
          value={formatCurrency(expenses.accrued_total)}
        />
        <DashboardKpiCard
          label="Pagado a proveedores"
          value={formatCurrency(expenses.paid_to_suppliers_total)}
        />
        <DashboardKpiCard
          label="Egresos de caja"
          value={formatCurrency(expenses.cash_expenses_total)}
        />
        <DashboardKpiCard
          label="Desembolso total"
          value={formatCurrency(expenses.disbursed_total)}
        />
      </CardContent>
    </Card>
  );
}
