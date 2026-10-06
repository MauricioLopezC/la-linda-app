import { Link, usePage } from '@inertiajs/react';
import { ShoppingCart } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { CashSessionIndicator } from '@/components/cash-session-indicator';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
  breadcrumbs = [],
}: {
  breadcrumbs?: BreadcrumbItemType[];
}) {
  const { auth, cartCount } = usePage().props;
  const isInternalStaff = auth?.user?.role === 'personal_interno';
  const effectiveCartCount = typeof cartCount === 'number' ? cartCount : 0;

  return (
    <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
      <div className="flex items-center gap-2">
        <SidebarTrigger className="-ml-1" />
        <Breadcrumbs breadcrumbs={breadcrumbs} />
      </div>

      <div className="ml-auto flex items-center gap-2">
        {isInternalStaff && <CashSessionIndicator />}

        <Button
          variant="ghost"
          size="icon"
          asChild
          className="relative text-muted-foreground hover:text-foreground"
          aria-label="Carrito de compras"
        >
          <Link href="/tienda/carrito">
            <ShoppingCart className="size-5" />
            {effectiveCartCount > 0 && (
              <Badge
                variant="destructive"
                className="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[11px] font-bold"
              >
                {effectiveCartCount > 99 ? '99+' : effectiveCartCount}
              </Badge>
            )}
          </Link>
        </Button>
      </div>
    </header>
  );
}
