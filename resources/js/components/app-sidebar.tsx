import { Link } from '@inertiajs/react';
import {
  BookOpen,
  Boxes,
  Building2,
  CreditCard,
  FileEdit,
  FolderTree,
  FolderGit2,
  Package,
  Percent,
  Ruler,
  Tags,
  LayoutGrid,
  Sliders,
  Store,
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
import { index as stockParameters } from '@/routes/inventory/parameters';
import { index as stocks } from '@/routes/inventory/stocks';
import { index as warehouses } from '@/routes/inventory/warehouses';
import { index as branches } from '@/routes/organization/branches';
import { index as vatRates } from '@/routes/pricing/vat-rates';
import { index as paymentMethods } from '@/routes/sales/payment-methods';
import { index as pointsOfSale } from '@/routes/sales/points-of-sale';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
  {
    title: 'Dashboard',
    href: dashboard(),
    icon: LayoutGrid,
  },
  {
    title: 'Existencias',
    href: stocks(),
    icon: Boxes,
  },
  {
    title: 'Ajuste de Stock',
    href: '/inventory/adjustments/create',
    icon: FileEdit,
  },
  {
    title: 'Sucursales',
    href: branches(),
    icon: Building2,
  },
  {
    title: 'Depósitos',
    href: warehouses(),
    icon: Warehouse,
  },
  {
    title: 'Puntos de Venta',
    href: pointsOfSale(),
    icon: Store,
  },
  {
    title: 'Medios de Pago',
    href: paymentMethods(),
    icon: CreditCard,
  },
  {
    title: 'Alícuotas de IVA',
    href: vatRates(),
    icon: Percent,
  },
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
  {
    title: 'Parámetros de Stock',
    href: stockParameters(),
    icon: Sliders,
  },
];

const footerNavItems: NavItem[] = [
  {
    title: 'Repositorio',
    href: 'https://github.com/laravel/react-starter-kit',
    icon: FolderGit2,
  },
  {
    title: 'Documentación',
    href: 'https://laravel.com/docs/starter-kits#react',
    icon: BookOpen,
  },
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
        <NavMain items={mainNavItems} />
      </SidebarContent>

      <SidebarFooter>
        <NavFooter items={footerNavItems} className="mt-auto" />
        <NavUser />
      </SidebarFooter>
    </Sidebar>
  );
}
