import { Head, Link, router, useForm } from '@inertiajs/react';
import { Eye, Plus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import TablePagination from '@/components/table-pagination';
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
import { index, show, store } from '@/routes/sales/sales';
import type { BreadcrumbItem } from '@/types';

type Sale = App.Data.Sales.SaleListData;
type PointOfSale = App.Data.Sales.PointOfSaleData;
type CustomerOption = App.Data.Sales.SaleCustomerOptionData;
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
  customers: CustomerOption[];
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
  customers = [],
  statuses = [],
  filters,
}: Props) {
  const [status, setStatus] = useState(filters.status ?? 'all');
  const [pointOfSaleId, setPointOfSaleId] = useState(
    filters.point_of_sale_id ?? 'all',
  );
  const [date, setDate] = useState(filters.date ?? '');
  const [isOpenDialogVisible, setIsOpenDialogVisible] = useState(false);

  const activePointsOfSale = pointsOfSale.filter((pos) => pos.is_active);
  const defaultCustomer = customers.find((customer) => customer.is_default);

  const openSaleForm = useForm({
    point_of_sale_id: '',
    customer_id: defaultCustomer ? String(defaultCustomer.id) : '',
  });

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

  const handleShowOpenDialog = () => {
    openSaleForm.setData({
      point_of_sale_id:
        activePointsOfSale.length === 1 ? String(activePointsOfSale[0].id) : '',
      customer_id: defaultCustomer ? String(defaultCustomer.id) : '',
    });
    openSaleForm.clearErrors();
    setIsOpenDialogVisible(true);
  };

  const handleOpenSale = (e: React.FormEvent) => {
    e.preventDefault();
    openSaleForm.post(store.url(), {
      onError: () => toast.error('No se pudo abrir la venta'),
    });
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
          <Button
            onClick={handleShowOpenDialog}
            disabled={activePointsOfSale.length === 0}
          >
            <Plus className="mr-1.5 size-4" />
            Abrir venta
          </Button>
        </div>

        {activePointsOfSale.length === 0 && (
          <p className="text-sm text-muted-foreground">
            Primero registrá al menos un punto de venta activo para poder abrir
            ventas.
          </p>
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
            <Label>Punto de venta</Label>
            <Select value={pointOfSaleId} onValueChange={setPointOfSaleId}>
              <SelectTrigger className="w-full sm:w-56">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos</SelectItem>
                {pointsOfSale.map((pos) => (
                  <SelectItem key={pos.id} value={String(pos.id)}>
                    PDV {pos.number} · {pos.branch_name}
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
                <TableHead>Fecha y hora</TableHead>
                <TableHead>Punto de venta</TableHead>
                <TableHead>Cliente</TableHead>
                <TableHead>Vendedor</TableHead>
                <TableHead className="text-right">Total</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {sales.data.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={8}
                    className="py-10 text-center text-muted-foreground"
                  >
                    No hay ventas para los filtros seleccionados.
                  </TableCell>
                </TableRow>
              ) : (
                sales.data.map((sale) => (
                  <TableRow key={sale.id}>
                    <TableCell className="font-mono">{sale.id}</TableCell>
                    <TableCell>{sale.opened_at_formatted}</TableCell>
                    <TableCell>
                      PDV {sale.point_of_sale_number} · {sale.branch_name}
                    </TableCell>
                    <TableCell>{sale.customer_name}</TableCell>
                    <TableCell>{sale.user_name ?? '—'}</TableCell>
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

      <Dialog open={isOpenDialogVisible} onOpenChange={setIsOpenDialogVisible}>
        <DialogContent>
          <form onSubmit={handleOpenSale} className="space-y-4">
            <DialogHeader>
              <DialogTitle>Abrir venta de mostrador</DialogTitle>
              <DialogDescription>
                La venta arranca con el cliente elegido y se puede cambiar
                después desde la pantalla de venta.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-1.5">
              <Label>Punto de venta</Label>
              <Select
                value={openSaleForm.data.point_of_sale_id}
                onValueChange={(value) =>
                  openSaleForm.setData('point_of_sale_id', value)
                }
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Seleccioná un punto de venta" />
                </SelectTrigger>
                <SelectContent>
                  {activePointsOfSale.map((pos) => (
                    <SelectItem key={pos.id} value={String(pos.id)}>
                      PDV {pos.number} · {pos.branch_name} ({pos.warehouse_name}
                      )
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <InputError message={openSaleForm.errors.point_of_sale_id} />
            </div>

            <div className="space-y-1.5">
              <Label>Cliente</Label>
              <Select
                value={openSaleForm.data.customer_id}
                onValueChange={(value) =>
                  openSaleForm.setData('customer_id', value)
                }
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Seleccioná un cliente" />
                </SelectTrigger>
                <SelectContent>
                  {customers.map((customer) => (
                    <SelectItem key={customer.id} value={String(customer.id)}>
                      {customer.name}
                      {customer.price_list_name
                        ? ` · ${customer.price_list_name}`
                        : ''}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <InputError message={openSaleForm.errors.customer_id} />
            </div>

            <p className="text-sm text-muted-foreground">
              Canal: <span className="font-medium">Mostrador</span>
            </p>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setIsOpenDialogVisible(false)}
              >
                Cancelar
              </Button>
              <Button
                type="submit"
                disabled={
                  openSaleForm.processing ||
                  openSaleForm.data.point_of_sale_id === ''
                }
              >
                Abrir venta
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}

SalesIndex.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Ventas', href: index() },
  ] satisfies BreadcrumbItem[],
};
