import { Link, usePage } from '@inertiajs/react';
import { Vault } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { create, show } from '@/routes/sales/cash-sessions';

/**
 * Shows the cashier where they are working (HU-057), or a shortcut to open a
 * cash session when they have none. Clicking the active session takes them to
 * the shift view (HU-058).
 */
export function CashSessionIndicator() {
  const { auth, cashSession } = usePage().props;

  if (auth?.user?.role !== 'personal_interno') {
    return null;
  }

  if (cashSession === null) {
    return (
      <Button asChild variant="outline" size="sm">
        <Link href={create()}>
          <Vault className="mr-1.5 size-4" />
          Abrir caja
        </Link>
      </Button>
    );
  }

  const openedAt = new Date(cashSession.opened_at).toLocaleTimeString('es-AR', {
    hour: '2-digit',
    minute: '2-digit',
  });

  return (
    <Button
      asChild
      variant="secondary"
      size="sm"
      className="h-auto gap-1.5 px-2.5 py-1 text-xs font-normal transition-colors hover:bg-secondary/80"
    >
      <Link href={show(cashSession.id)}>
        <Vault className="size-3.5" />
        <span>
          Caja {cashSession.point_of_sale_number} · {cashSession.branch_name} ·
          desde las {openedAt}
        </span>
      </Link>
    </Button>
  );
}
