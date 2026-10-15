import type { FC } from 'react';
import DashboardSectionPlaceholder from './dashboard-section-placeholder';

type Props = {
  operational: App.Data.Reporting.DashboardOperationalData | null;
};

const DashboardOperationalSection: FC<Props> = () => {
  return (
    <DashboardSectionPlaceholder
      title="Indicadores operativos"
      description="Artículos más vendidos, compras por proveedor y alertas de stock y despacho."
      story="EPIC-21"
    />
  );
};

export default DashboardOperationalSection;
