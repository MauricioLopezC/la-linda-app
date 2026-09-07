import { Head, Link, router } from '@inertiajs/react';
import {
  Download,
  Eye,
  FileText,
  Filter,
  Pencil,
  Plus,
  RotateCcw,
  Search,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import TablePagination from '@/components/table-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { create, edit, index, pdf, show } from '@/routes/purchasing/orders';
import type { BreadcrumbItem } from '@/types';

type Order = App.Data.Purchasing.PurchaseOrderListData;
type SupplierOption = App.Data.Purchasing.SupplierOptionData;
type WarehouseOption = App.Data.Purchasing.PurchaseOrderWarehouseOptionData;
type StatusOption = { value: string; label: string };

type PaginationProps = {
  data: Order[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

type Props = {
  orders: PaginationProps;
  suppliers: SupplierOption[];
  warehouses: WarehouseOption[];
  statuses: StatusOption[];
  filters: {
    search: string;
    supplier_id: string;
    warehouse_id: string;
    status: string;
    date_from: string;
    date_to: string;
  };
};

const statusClasses: Record<string, string> = {
  borrador:
    'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
  emitida:
    'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
  cancelada:
    'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300',
};

export default function PurchaseOrdersIndex({
  orders,
  suppliers = [],
  warehouses = [],
  statuses = [],
  filters,
}: Props) {
  const [search, setSearch] = useState(filters.search ?? '');
  const [supplierId, setSupplierId] = useState(filters.supplier_id ?? 'all');
  const [warehouseId, setWarehouseId] = useState(filters.warehouse_id ?? 'all');
  const [status, setStatus] = useState(filters.status ?? 'all');
  const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
  const [dateTo, setDateTo] = useState(filters.date_to ?? '');

  const applyFilters = () => {
    router.get(
      index.url({
        query: {
          search: search || undefined,
          supplier_id: supplierId !== 'all' ? supplierId : undefined,
          warehouse_id: warehouseId !== 'all' ? warehouseId : undefined,
          status: status !== 'all' ? status : undefined,
          date_from: dateFrom || undefined,
          date_to: dateTo || undefined,
        },
      }),
      {},
      { preserveState: true, preserveScroll: true },
    );
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter') {
      applyFilters();
    }
  };

  const resetFilters = () => {
    setSearch('');
    setSupplierId('all');
    setWarehouseId('all');
    setStatus('all');
    setDateFrom('');
    setDateTo('');
    router.get(index.url(), {}, { preserveState: true, preserveScroll: true });
  };

  const changePage = (page: number) => {
    router.get(
      index.url({
        query: {
          page,
          search: search || undefined,
          supplier_id: supplierId !== 'all' ? supplierId : undefined,
          warehouse_id: warehouseId !== 'all' ? warehouseId : undefined,
          status: status !== 'all' ? status : undefined,
          date_from: dateFrom || undefined,
          date_to: dateTo || undefined,
        },
      }),
      {},
      { preserveState: true, preserveScroll: true },
    );
  };

  const hasActiveFilters =
    search !== '' ||
    supplierId !== 'all' ||
    warehouseId !== 'all' ||
    status !== 'all' ||
    dateFrom !== '' ||
    dateTo !== '';

  return (
    <>
      <Head title="Órdenes de compra" />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <Heading
            title="Órdenes de compra"
            description="Gestión, emisión y seguimiento de solicitudes de compra a proveedores."
          />
          <Button asChild className="gap-2 self-start sm:self-auto">
            <Link href={create.url()}>
              <Plus className="size-4" />
              Nueva orden de compra
            </Link>
          </Button>
        </div>

        {/* Filtros */}
        <Card className="border bg-card shadow-xs">
          <CardContent className="p-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-12">
              <div className="min-w-0 space-y-1.5 sm:col-span-2 md:col-span-3 lg:col-span-4">
                <Label htmlFor="search">Buscar</Label>
                <div className="relative">
                  <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                  <Input
                    id="search"
                    placeholder="Nro. orden o proveedor..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    onKeyDown={handleKeyDown}
                    className="pl-8"
                  />
                </div>
              </div>

              <div className="min-w-0 space-y-1.5 sm:col-span-1 md:col-span-1 lg:col-span-3">
                <Label htmlFor="supplier">Proveedor</Label>
                <Select value={supplierId} onValueChange={setSupplierId}>
                  <SelectTrigger id="supplier" className="w-full min-w-0">
                    <SelectValue placeholder="Todos los proveedores" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todos los proveedores</SelectItem>
                    {suppliers.map((s) => (
                      <SelectItem key={s.id} value={String(s.id)}>
                        {s.business_name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="min-w-0 space-y-1.5 sm:col-span-1 md:col-span-1 lg:col-span-3">
                <Label htmlFor="warehouse">Depósito</Label>
                <Select value={warehouseId} onValueChange={setWarehouseId}>
                  <SelectTrigger id="warehouse" className="w-full min-w-0">
                    <SelectValue placeholder="Todos los depósitos" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todos los depósitos</SelectItem>
                    {warehouses.map((w) => (
                      <SelectItem key={w.id} value={String(w.id)}>
                        {w.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="min-w-0 space-y-1.5 sm:col-span-1 md:col-span-1 lg:col-span-2">
                <Label htmlFor="status">Estado</Label>
                <Select value={status} onValueChange={setStatus}>
                  <SelectTrigger id="status" className="w-full min-w-0">
                    <SelectValue placeholder="Todos los estados" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todos los estados</SelectItem>
                    {statuses.map((st) => (
                      <SelectItem key={st.value} value={st.value}>
                        {st.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="min-w-0 space-y-1.5 sm:col-span-1 md:col-span-1 lg:col-span-3">
                <Label htmlFor="date_from">Emisión desde</Label>
                <Input
                  id="date_from"
                  type="date"
                  value={dateFrom}
                  onChange={(e) => setDateFrom(e.target.value)}
                />
              </div>

              <div className="min-w-0 space-y-1.5 sm:col-span-1 md:col-span-1 lg:col-span-3">
                <Label htmlFor="date_to">Emisión hasta</Label>
                <Input
                  id="date_to"
                  type="date"
                  value={dateTo}
                  onChange={(e) => setDateTo(e.target.value)}
                />
              </div>

              <div className="flex items-end gap-2 sm:col-span-2 md:col-span-1 lg:col-span-6 lg:justify-end">
                <Button onClick={applyFilters} className="gap-2 sm:w-auto">
                  <Filter className="size-4" />
                  Filtrar
                </Button>
                {hasActiveFilters && (
                  <Button
                    variant="outline"
                    onClick={resetFilters}
                    title="Limpiar filtros"
                    className="px-2.5"
                  >
                    <RotateCcw className="size-4" />
                  </Button>
                )}
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Tabla */}
        <div className="rounded-lg border bg-card shadow-xs">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nro. de orden</TableHead>
                <TableHead>Proveedor</TableHead>
                <TableHead>Depósito destino</TableHead>
                <TableHead>Fecha emisión</TableHead>
                <TableHead>Entrega esperada</TableHead>
                <TableHead className="text-center">Artículos</TableHead>
                <TableHead className="text-right">Total pactado</TableHead>
                <TableHead className="text-center">Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {orders.data.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={9}
                    className="py-12 text-center text-muted-foreground"
                  >
                    <FileText className="mx-auto mb-2 size-8 opacity-40" />
                    No se encontraron órdenes de compra registradas.
                  </TableCell>
                </TableRow>
              ) : (
                orders.data.map((order) => (
                  <TableRow key={order.id} className="hover:bg-muted/50">
                    <TableCell className="font-mono font-bold">
                      <Link
                        href={show.url({ purchase_order: order.id })}
                        className="text-primary hover:underline"
                      >
                        {order.order_number}
                      </Link>
                    </TableCell>
                    <TableCell className="font-medium">
                      {order.supplier_name}
                    </TableCell>
                    <TableCell className="text-muted-foreground">
                      {order.warehouse_name}
                    </TableCell>
                    <TableCell>{order.issue_date_formatted}</TableCell>
                    <TableCell className="text-muted-foreground">
                      {order.expected_delivery_date_formatted ?? 'A convenir'}
                    </TableCell>
                    <TableCell className="text-center">
                      <span className="inline-flex size-6 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                        {order.items_count}
                      </span>
                    </TableCell>
                    <TableCell className="text-right font-medium">
                      {formatCurrency(order.total_amount)}
                    </TableCell>
                    <TableCell className="text-center">
                      <Badge
                        variant="outline"
                        className={statusClasses[order.status] ?? ''}
                      >
                        {order.status_label}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex items-center justify-end gap-1">
                        <Button
                          variant="ghost"
                          size="icon"
                          asChild
                          title="Ver detalle de la orden"
                        >
                          <Link href={show.url({ purchase_order: order.id })}>
                            <Eye className="size-4" />
                          </Link>
                        </Button>
                        {order.can_edit && (
                          <Button
                            variant="ghost"
                            size="icon"
                            asChild
                            title="Editar borrador"
                          >
                            <Link href={edit.url({ purchase_order: order.id })}>
                              <Pencil className="size-4" />
                            </Link>
                          </Button>
                        )}
                        <Button
                          variant="ghost"
                          size="icon"
                          asChild
                          title="Descargar PDF"
                        >
                          <a
                            href={pdf.url({ purchase_order: order.id })}
                            target="_blank"
                            rel="noopener noreferrer"
                          >
                            <Download className="size-4" />
                          </a>
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>

        <TablePagination
          currentPage={orders.current_page}
          totalPages={orders.last_page}
          totalItems={orders.total}
          pageSize={orders.per_page}
          onPageChange={changePage}
          entityName="órdenes de compra"
        />
      </div>
    </>
  );
}

PurchaseOrdersIndex.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Compras', href: '#' },
    { title: 'Órdenes de compra', href: index() },
  ] satisfies BreadcrumbItem[],
};
