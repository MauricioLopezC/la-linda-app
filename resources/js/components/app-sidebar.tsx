import { Link, usePage } from '@inertiajs/react';
import {
  Banknote,
  Boxes,
  Building2,
  CreditCard,
  FileEdit,
  FolderTree,
  FolderGit2,
  History,
  Landmark,
  Package,
  Percent,
  ReceiptText,
  Ruler,
  ShoppingBag,
  ShoppingCart,
  Tags,
  LayoutGrid,
  Sliders,
  Truck,
  Users,
  Vault,
  Wallet,
  Warehouse,
  Store,
  LogIn,
  UserPlus,
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
import { dashboard, login, register } from '@/routes';
import { index as articles } from '@/routes/catalog/articles';
import { index as brands } from '@/routes/catalog/brands';
import { index as categories } from '@/routes/catalog/categories';
import { index as unitsOfMeasure } from '@/routes/catalog/units-of-measure';
import { index as customers } from '@/routes/customers';
import { create as adjustmentsCreate } from '@/routes/inventory/adjustments';
import { index as movements } from '@/routes/inventory/movements';
import { index as stockParameters } from '@/routes/inventory/parameters';
import { index as stocks } from '@/routes/inventory/stocks';
import { index as warehouses } from '@/routes/inventory/warehouses';
import { index as branches } from '@/routes/organization/branches';
import { index as priceLists } from '@/routes/pricing/price-lists';
import { index as vatRates } from '@/routes/pricing/vat-rates';
import { index as accountStatement } from '@/routes/purchasing/account-statement';
import { index as purchaseOrders } from '@/routes/purchasing/orders';
import { index as paymentOrders } from '@/routes/purchasing/payment-orders';
import { index as suppliers } from '@/routes/purchasing/suppliers';
import { index as supplierVouchers } from '@/routes/purchasing/vouchers';
import { current as currentCashSession } from '@/routes/sales/cash-sessions';
import { index as paymentMethods } from '@/routes/sales/payment-methods';
import { index as pointsOfSale } from '@/routes/sales/points-of-sale';
import { index as sales } from '@/routes/sales/sales';
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
      {
        title: 'Órdenes de Compra',
        href: purchaseOrders(),
        icon: ShoppingCart,
      },
      {
        title: 'Comprobantes',
        href: supplierVouchers(),
        icon: ReceiptText,
      },
      {
        title: 'Órdenes de Pago',
        href: paymentOrders(),
        icon: Wallet,
      },
      {
        title: 'Cuenta Corriente',
        href: accountStatement(),
        icon: Landmark,
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
      {
        title: 'Puntos de Venta',
        href: pointsOfSale(),
        icon: Store,
      },
    ],
  },
  {
    label: 'Ventas',
    items: [
      {
        title: 'Turno de caja',
        href: currentCashSession(),
        icon: Vault,
      },
      {
        title: 'Ventas',
        href: sales(),
        icon: ShoppingBag,
      },
      {
        title: 'Clientes',
        href: customers(),
        icon: Users,
      },
      {
        title: 'Medios de Pago',
        href: paymentMethods(),
        icon: CreditCard,
      },
    ],
  },
  {
    label: 'Precios',
    items: [
      {
        title: 'Listas de Precios',
        href: priceLists(),
        icon: Banknote,
      },
      {
        title: 'Alícuotas de IVA',
        href: vatRates(),
        icon: Percent,
      },
    ],
  },
  {
    label: 'Tienda Online',
    items: [
      {
        title: 'Ir a la tienda online',
        href: '/tienda',
        icon: Store,
      },
    ],
  },
];

const clientNavGroups: NavGroup[] = [
  {
    label: 'Tienda Online',
    items: [
      {
        title: 'Tienda Online',
        href: '/tienda',
        icon: Store,
      },
      {
        title: 'Mi cuenta',
        href: '/tienda/mi-cuenta',
        icon: Users,
      },
    ],
  },
];

const guestNavGroups: NavGroup[] = [
  {
    label: 'Tienda Online',
    items: [
      {
        title: 'Tienda Online',
        href: '/tienda',
        icon: Store,
      },
      {
        title: 'Iniciar sesión',
        href: login(),
        icon: LogIn,
      },
      {
        title: 'Registrarse',
        href: register(),
        icon: UserPlus,
      },
    ],
  },
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
  const { auth } = usePage().props;
  const isInternalStaff = auth?.user?.role === 'personal_interno';
  const isClient = auth?.user?.role === 'cliente';

  const groups = isInternalStaff
    ? navGroups
    : isClient
      ? clientNavGroups
      : guestNavGroups;

  return (
    <Sidebar collapsible="icon" variant="inset">
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" asChild>
              <Link href={isInternalStaff ? dashboard() : '/tienda'} prefetch>
                <AppLogo />
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        <NavMain groups={groups} />
      </SidebarContent>

      <SidebarFooter>
        <NavFooter items={footerNavItems} className="mt-auto" />
        <NavUser />
      </SidebarFooter>
    </Sidebar>
  );
}
