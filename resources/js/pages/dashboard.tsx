import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { dashboard } from '@/routes';
import DashboardEvolutionSection from './dashboard/partials/dashboard-evolution-section';
import DashboardExpensesSection from './dashboard/partials/dashboard-expenses-section';
import DashboardFilters from './dashboard/partials/dashboard-filters';
import DashboardOperationalSection from './dashboard/partials/dashboard-operational-section';
import DashboardRevenuesSection from './dashboard/partials/dashboard-revenues-section';

type Props = App.Data.Reporting.DashboardData;

export default function Dashboard({
  filters,
  branches,
  revenues,
  expenses,
}: Props) {
  return (
    <>
      <Head title="Tablero gerencial" />
      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <Heading
          title="Tablero gerencial"
          description="Ingresos, egresos y resultado del negocio en el período seleccionado."
        />

        <DashboardFilters
          key={JSON.stringify(filters)}
          filters={filters}
          branches={branches}
        />

        <DashboardRevenuesSection revenues={revenues} />
        <DashboardExpensesSection expenses={expenses} />
        <DashboardEvolutionSection />
        <DashboardOperationalSection />
      </div>
    </>
  );
}

Dashboard.layout = {
  breadcrumbs: [
    {
      title: 'Dashboard',
      href: dashboard(),
    },
  ],
};
