import type { FC } from 'react';
import DashboardSectionPlaceholder from './dashboard-section-placeholder';

type Props = {
  evolution: App.Data.Reporting.DashboardEvolutionData | null;
};

const DashboardEvolutionSection: FC<Props> = () => {
  return (
    <DashboardSectionPlaceholder
      title="Resultado neto y evolución"
      description="Ingresos menos egresos devengados y su evolución en el período."
      story="EPIC-20"
    />
  );
};

export default DashboardEvolutionSection;
