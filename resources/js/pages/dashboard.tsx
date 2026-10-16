import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { dashboard } from '@/routes';
import DashboardEvolutionSection from './dashboard/partials/dashboard-evolution-section';
import DashboardExpensesSection from './dashboard/partials/dashboard-expenses-section';
import DashboardExportDropdown from './dashboard/partials/dashboard-export-dropdown';
import DashboardFilters from './dashboard/partials/dashboard-filters';
import DashboardOperationalSection from './dashboard/partials/dashboard-operational-section';
import DashboardRevenuesSection from './dashboard/partials/dashboard-revenues-section';

type Props = App.Data.Reporting.DashboardData;

export default function Dashboard({
  filters,
  branches,
  revenues,
  expenses,
  evolution,
  operational,
}: Props) {
  return (
    <>
      <Head title="Tablero gerencial" />
      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <Heading
            title="Tablero gerencial"
            description="Ingresos, egresos y resultado del negocio en el período seleccionado."
          />
          <div className="flex items-center gap-2">
            <DashboardExportDropdown filters={filters} />
          </div>
        </div>

        <DashboardFilters
          key={JSON.stringify(filters)}
          filters={filters}
          branches={branches}
        />

        <DashboardRevenuesSection revenues={revenues} />
        <DashboardExpensesSection expenses={expenses} filters={filters} />
        <DashboardEvolutionSection evolution={evolution} filters={filters} />
        <DashboardOperationalSection operational={operational} />
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
