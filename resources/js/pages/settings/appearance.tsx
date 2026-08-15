import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
  return (
    <>
      <Head title="Configuración de apariencia" />

      <h1 className="sr-only">Configuración de apariencia</h1>

      <div className="space-y-6">
        <Heading
          variant="small"
          title="Configuración de apariencia"
          description="Actualizá la configuración de apariencia de tu cuenta"
        />
        <AppearanceTabs />
      </div>
    </>
  );
}

Appearance.layout = {
  breadcrumbs: [
    {
      title: 'Configuración de apariencia',
      href: editAppearance(),
    },
  ],
};
