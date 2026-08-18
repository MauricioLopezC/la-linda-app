import { Link } from '@inertiajs/react';
import {
  BookOpen,
  Building2,
  FolderTree,
  FolderGit2,
  Ruler,
  Tags,
  LayoutGrid,
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
import { index as brands } from '@/routes/catalog/brands';
import { index as categories } from '@/routes/catalog/categories';
import { index as unitsOfMeasure } from '@/routes/catalog/units-of-measure';
import { index as warehouses } from '@/routes/inventory/warehouses';
import { index as branches } from '@/routes/organization/branches';
import { index as pointsOfSale } from '@/routes/sales/points-of-sale';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
  {
    title: 'Dashboard',
    href: dashboard(),
    icon: LayoutGrid,
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
