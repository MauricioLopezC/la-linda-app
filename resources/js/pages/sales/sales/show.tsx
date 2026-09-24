import { Head, router } from '@inertiajs/react';
import { Ban } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { dashboard } from '@/routes';
import { discard, index } from '@/routes/sales/sales';
import type { BreadcrumbItem } from '@/types';

type Sale = App.Data.Sales.SaleData;

type Props = {
  sale: Sale;
};

const saleStatusClasses: Record<string, string> = {
  abierta:
    'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
  descartada:
    'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300',
};

/**
 * Show the first validation error of a failed sale request as a toast.
 */
function toastFirstError(errors: Record<string, string>): void {
  const message = Object.values(errors)[0];

  toast.error(message ?? 'No se pudo completar la operación');
}

export default function SaleShow({ sale }: Props) {
  const [isDiscardDialogOpen, setIsDiscardDialogOpen] = useState(false);

  const handleDiscard = () => {
    router.post(
      discard.url(sale.id),
      {},
      {
        onSuccess: () => toast.success('Venta descartada'),
        onError: toastFirstError,
        onFinish: () => setIsDiscardDialogOpen(false),
      },
    );
  };

  return (
    <>
      <Head title={`Venta N° ${sale.id}`} />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
          <div className="flex items-center gap-3">
            <Heading
              title={`Venta N° ${sale.id}`}
              description={`${sale.branch_name} · PDV ${sale.point_of_sale_number} · ${sale.opened_at_formatted}`}
            />
            <Badge variant="outline" className={saleStatusClasses[sale.status]}>
              {sale.status_label}
            </Badge>
          </div>
          {sale.is_open && (
            <Button
              variant="outline"
              className="text-destructive"
              onClick={() => setIsDiscardDialogOpen(true)}
            >
              <Ban className="mr-1.5 size-4" />
              Descartar venta
            </Button>
          )}
        </div>

        <div className="grid gap-4 rounded-xl border border-sidebar-border bg-card p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
          <InfoField label="Canal" value={sale.channel_label} />
          <InfoField label="Depósito" value={sale.warehouse_name} />
          <InfoField label="Vendedor" value={sale.user_name ?? '—'} />
          <div className="space-y-1.5">
            <InfoField label="Cliente" value={sale.customer_name} />
            {sale.customer_price_list_name && (
              <p className="text-xs text-muted-foreground">
                Lista asignada: {sale.customer_price_list_name}
              </p>
            )}
          </div>
        </div>
      </div>

      <Dialog open={isDiscardDialogOpen} onOpenChange={setIsDiscardDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>¿Descartar la venta N° {sale.id}?</DialogTitle>
            <DialogDescription>
              La venta queda descartada y ya no se puede modificar.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setIsDiscardDialogOpen(false)}
            >
              Volver
            </Button>
            <Button variant="destructive" onClick={handleDiscard}>
              Descartar venta
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

function InfoField({ label, value }: { label: string; value: string }) {
  return (
    <div className="space-y-1.5">
      <p className="text-sm font-medium text-muted-foreground">{label}</p>
      <p className="text-sm font-medium">{value}</p>
    </div>
  );
}

SaleShow.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Ventas', href: index() },
    { title: 'Detalle', href: '#' },
  ] satisfies BreadcrumbItem[],
};
