import { Link } from '@inertiajs/react';
import {
  Boxes,
  Building2,
  FileEdit,
  FolderTree,
  FolderGit2,
  History,
  Package,
  Ruler,
  Tags,
  LayoutGrid,
  Sliders,
  Truck,
  Warehouse,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as articles } from '@/routes/catalog/articles';
import { index as brands } from '@/routes/catalog/brands';
import { index as categories } from '@/routes/catalog/categories';
import { index as unitsOfMeasure } from '@/routes/catalog/units-of-measure';
import { create as adjustmentsCreate } from '@/routes/inventory/adjustments';
import { index as movements } from '@/routes/inventory/movements';
import { index as stockParameters } from '@/routes/inventory/parameters';
import { index as stocks } from '@/routes/inventory/stocks';
import { index as warehouses } from '@/routes/inventory/warehouses';
import { index as branches } from '@/routes/organization/branches';
import { index as suppliers } from '@/routes/purchasing/suppliers';
// Módulos "Alícuotas de IVA" y "Medios de Pago"/"Puntos de Venta" no están en el
// alcance de este sprint; se ocultan del menú sin borrar el código que los soporta.
// import { index as vatRates } from '@/routes/pricing/vat-rates';
// import { index as paymentMethods } from '@/routes/sales/payment-methods';
// import { index as pointsOfSale } from '@/routes/sales/points-of-sale';
import type { NavGroup, NavItem } from '@/types';

const navGroups: NavGroup[] = [
  {
    label: 'General',
    items: [
      {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
      },
    ],
  },
  {
    label: 'Catálogo',
    items: [
      {
        title: 'Artículos',
        href: articles(),
        icon: Package,
      },
      {
        title: 'Categorías',
        href: categories(),
        icon: FolderTree,
      },
      {
        title: 'Marcas',
        href: brands(),
        icon: Tags,
      },
      {
        title: 'Unidades de medida',
        href: unitsOfMeasure(),
        icon: Ruler,
      },
    ],
  },
  {
    label: 'Inventario',
    items: [
      {
        title: 'Existencias',
        href: stocks(),
        icon: Boxes,
      },
      {
        title: 'Ajuste de Stock',
        href: adjustmentsCreate(),
        icon: FileEdit,
      },
      {
        title: 'Historial de Movimientos',
        href: movements(),
        icon: History,
      },
      {
        title: 'Depósitos',
        href: warehouses(),
        icon: Warehouse,
      },
      {
        title: 'Parámetros de Stock',
        href: stockParameters(),
        icon: Sliders,
      },
    ],
  },
  {
    label: 'Compras',
    items: [
      {
        title: 'Proveedores',
        href: suppliers(),
        icon: Truck,
      },
    ],
  },
  {
    label: 'Organización',
    items: [
      {
        title: 'Sucursales',
        href: branches(),
        icon: Building2,
      },
      // Fuera de alcance de este sprint: ver nota sobre imports comentados arriba.
      // {
      //   title: 'Puntos de Venta',
      //   href: pointsOfSale(),
      //   icon: Store,
      // },
    ],
  },
  // Fuera de alcance de este sprint: ver nota sobre imports comentados arriba.
  // {
  //   label: 'Ventas',
  //   items: [
  //     {
  //       title: 'Medios de Pago',
  //       href: paymentMethods(),
  //       icon: CreditCard,
  //     },
  //   ],
  // },
  // {
  //   label: 'Precios',
  //   items: [
  //     {
  //       title: 'Alícuotas de IVA',
  //       href: vatRates(),
  //       icon: Percent,
  //     },
  //   ],
  // },
];

const footerNavItems: NavItem[] = [
  {
    title: 'Repositorio',
    href: 'https://github.com/MauricioLopezC/la-linda-app',
    icon: FolderGit2,
  },
  // Todavía no hay un destino para la documentación del proyecto.
  // {
  //   title: 'Documentación',
  //   href: '',
  //   icon: BookOpen,
  // },
];

export function AppSidebar() {
  return (
    <Sidebar collapsible="icon" variant="inset">
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" asChild>
              <Link href={dashboard()} prefetch>
                <AppLogo />
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        <NavMain groups={navGroups} />
      </SidebarContent>

      <SidebarFooter>
        <NavFooter items={footerNavItems} className="mt-auto" />
        <NavUser />
      </SidebarFooter>
    </Sidebar>
  );
}
