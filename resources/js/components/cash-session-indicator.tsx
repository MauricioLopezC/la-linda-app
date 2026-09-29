import { Link, usePage } from '@inertiajs/react';
import { Vault } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create } from '@/routes/sales/cash-sessions';

/**
 * Shows the cashier where they are working (HU-057), or a shortcut to open a
 * cash session when they have none.
 */
export function CashSessionIndicator() {
  const { cashSession } = usePage().props;

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
    <Badge variant="secondary" className="gap-1.5 py-1">
      <Vault className="size-3.5" />
      Caja {cashSession.point_of_sale_number} · {cashSession.branch_name} ·
      desde las {openedAt}
    </Badge>
  );
}
