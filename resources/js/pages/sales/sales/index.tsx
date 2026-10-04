import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, Loader2, Plus, Store, Vault } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import TablePagination from '@/components/table-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { formatCurrency } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
  create as createCashSession,
  show as showCashSession,
} from '@/routes/sales/cash-sessions';
import { index, show, store } from '@/routes/sales/sales';
import type { BreadcrumbItem } from '@/types';

type Sale = App.Data.Sales.SaleListData;
type PointOfSale = App.Data.Sales.PointOfSaleData;
type StatusOption = { value: string; label: string };

type PaginationProps = {
  data: Sale[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

type Props = {
  sales: PaginationProps;
  pointsOfSale: PointOfSale[];
  statuses: StatusOption[];
  filters: {
    status: string;
    point_of_sale_id: string;
    date: string;
  };
};

const saleStatusClasses: Record<string, string> = {
  abierta:
    'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
  descartada:
    'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300',
};

export default function SalesIndex({
  sales,
  pointsOfSale = [],
  statuses = [],
  filters,
}: Props) {
  const { cashSession } = usePage().props;
  const [status, setStatus] = useState(filters.status ?? 'all');
  const [pointOfSaleId, setPointOfSaleId] = useState(
    filters.point_of_sale_id ?? 'all',
  );
  const [date, setDate] = useState(filters.date ?? '');
  const [isOpening, setIsOpening] = useState(false);

  const visit = (query: Record<string, string | number | undefined>) => {
    router.get(index.url({ query }), {}, { preserveState: true });
  };

  const applyFilters = (page?: number) => {
    visit({
      status: status !== 'all' ? status : undefined,
      point_of_sale_id: pointOfSaleId !== 'all' ? pointOfSaleId : undefined,
      date: date || undefined,
      page,
    });
  };

  const resetFilters = () => {
    setStatus('all');
    setPointOfSaleId('all');
    setDate('');
    visit({});
  };

  const handleOpenSale = () => {
    setIsOpening(true);
    router.post(
      store.url(),
      {},
      {
        onError: (errors) => {
          const message = Object.values(errors)[0] as string | undefined;
          toast.error(message ?? 'No se pudo abrir la venta');
        },
        onFinish: () => setIsOpening(false),
      },
    );
  };

  return (
    <>
      <Head title="Ventas" />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <Heading
            title="Ventas"
            description="Ventas de mostrador abiertas y descartadas."
          />
          {cashSession !== null ? (
            <div className="flex flex-wrap items-center gap-2">
              <Button asChild variant="outline">
                <Link href={showCashSession.url(cashSession.id)}>
                  <Vault className="mr-1.5 size-4" />
                  Ver mi turno
                </Link>
              </Button>
              <Button onClick={handleOpenSale} disabled={isOpening}>
                {isOpening ? (
                  <Loader2 className="mr-1.5 size-4 animate-spin" />
                ) : (
                  <Plus className="mr-1.5 size-4" />
                )}
                Abrir venta
              </Button>
            </div>
          ) : (
            <Button asChild>
              <Link href={createCashSession.url()}>
                <Store className="mr-1.5 size-4" />
                Abrir caja
              </Link>
            </Button>
          )}
        </div>

        {cashSession === null && (
          <div className="flex flex-col gap-2 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
            <div>
              <p className="font-medium">No tenés un turno de caja abierto</p>
              <p className="text-xs text-amber-800/90 dark:text-amber-300/80">
                Para abrir ventas de mostrador es necesario abrir primero tu
                turno de caja.
              </p>
            </div>
            <Button
              size="sm"
              variant="outline"
              className="border-amber-300 dark:border-amber-800"
              asChild
            >
              <Link href={createCashSession.url()}>Abrir caja</Link>
            </Button>
          </div>
        )}

        <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
          <div className="space-y-1.5">
            <Label>Estado</Label>
            <Select value={status} onValueChange={setStatus}>
              <SelectTrigger className="w-full sm:w-44">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos</SelectItem>
                {statuses.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label>Caja</Label>
            <Select value={pointOfSaleId} onValueChange={setPointOfSaleId}>
              <SelectTrigger className="w-full sm:w-56">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todas</SelectItem>
                {pointsOfSale.map((pos) => (
                  <SelectItem key={pos.id} value={String(pos.id)}>
                    Caja {pos.number} · {pos.branch_name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="date">Fecha</Label>
            <Input
              id="date"
              type="date"
              value={date}
              onChange={(e) => setDate(e.target.value)}
              className="w-full sm:w-44"
            />
          </div>
          <div className="flex gap-2">
            <Button variant="secondary" onClick={() => applyFilters()}>
              Filtrar
            </Button>
            <Button variant="ghost" onClick={resetFilters}>
              Limpiar
            </Button>
          </div>
        </div>

        <div className="overflow-x-auto rounded-xl border border-sidebar-border bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>N°</TableHead>
                <TableHead>Turno</TableHead>
                <TableHead>Fecha y hora</TableHead>
                <TableHead>Caja</TableHead>
                <TableHead>Cliente</TableHead>
                <TableHead>Vendedor</TableHead>
                <TableHead className="text-right">Líneas</TableHead>
                <TableHead className="text-right">Total</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {sales.data.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={10}
                    className="py-10 text-center text-muted-foreground"
                  >
                    No hay ventas para los filtros seleccionados.
                  </TableCell>
                </TableRow>
              ) : (
                sales.data.map((sale) => (
                  <TableRow key={sale.id}>
                    <TableCell className="font-mono">{sale.id}</TableCell>
                    <TableCell>
                      {sale.cash_session_id ? (
                        <Link
                          href={showCashSession.url(sale.cash_session_id)}
                          className="font-medium text-primary underline underline-offset-2 hover:opacity-80"
                        >
                          Turno #{sale.cash_session_id}
                        </Link>
                      ) : (
                        '—'
                      )}
                    </TableCell>
                    <TableCell>{sale.opened_at_formatted}</TableCell>
                    <TableCell>
                      Caja {sale.point_of_sale_number} · {sale.branch_name}
                    </TableCell>
                    <TableCell>{sale.customer_name}</TableCell>
                    <TableCell>{sale.user_name ?? '—'}</TableCell>
                    <TableCell className="text-right">
                      {sale.items_count}
                    </TableCell>
                    <TableCell className="text-right font-medium">
                      {formatCurrency(sale.total_amount)}
                    </TableCell>
                    <TableCell>
                      <Badge
                        variant="outline"
                        className={saleStatusClasses[sale.status]}
                      >
                        {sale.status_label}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <Button variant="ghost" size="sm" asChild>
                        <Link href={show(sale.id)}>
                          <Eye className="mr-1.5 size-4" />
                          Ver
                        </Link>
                      </Button>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>

        <TablePagination
          currentPage={sales.current_page}
          totalPages={sales.last_page}
          totalItems={sales.total}
          pageSize={sales.per_page}
          onPageChange={(page) => applyFilters(page)}
          entityName="ventas"
        />
      </div>
    </>
  );
}

SalesIndex.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Ventas', href: index() },
  ] satisfies BreadcrumbItem[],
};
