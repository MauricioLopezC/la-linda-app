import { Head, Link, router } from '@inertiajs/react';
import {
  AlertTriangle,
  CheckCircle2,
  Clock,
  Eye,
  Filter,
  Package,
  RotateCcw,
  Search,
  Truck,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import TablePagination from '@/components/table-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import {
  deliver,
  index,
  show,
  startPreparation,
} from '@/routes/ecommerce/orders';

type Order = App.Data.Ecommerce.WarehouseWebOrderListData;

type PaginationProps = {
  data: Order[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

type Props = {
  orders: PaginationProps;
  counts: {
    paid: number;
    in_preparation: number;
    delivered: number;
  };
  filters: {
    status: string;
    delivery_method: string;
    pickup_branch_id: number | null;
    date_from: string | null;
    date_to: string | null;
    search: string | null;
  };
  branches: Array<{ id: number; name: string }>;
};

const statusBadgeClasses: Record<string, string> = {
  pagado: 'border-warning-fg/25 bg-warning-bg text-warning-fg',
  en_preparacion: 'border-info-fg/25 bg-info-bg text-info-fg',
  entregado: 'border-success-fg/25 bg-success-bg text-success-fg',
};

export default function WarehouseOrdersIndex({
  orders,
  counts,
  filters,
  branches = [],
}: Props) {
  const [search, setSearch] = useState(filters.search ?? '');
  const [status, setStatus] = useState(filters.status ?? 'todos');
  const [deliveryMethod, setDeliveryMethod] = useState(
    filters.delivery_method ?? 'todos',
  );
  const [pickupBranchId, setPickupBranchId] = useState<string>(
    filters.pickup_branch_id ? String(filters.pickup_branch_id) : 'all',
  );
  const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
  const [dateTo, setDateTo] = useState(filters.date_to ?? '');

  // Deliver confirmation modal state
  const [deliveringOrder, setDeliveringOrder] = useState<Order | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const applyFilters = (overrideStatus?: string) => {
    const targetStatus = overrideStatus !== undefined ? overrideStatus : status;

    router.get(
      index.url({
        query: {
          search: search || undefined,
          status: targetStatus !== 'todos' ? targetStatus : undefined,
          delivery_method:
            deliveryMethod !== 'todos' ? deliveryMethod : undefined,
          pickup_branch_id:
            pickupBranchId !== 'all' ? pickupBranchId : undefined,
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

  const handleStatusCardClick = (newStatus: string) => {
    const updated = status === newStatus ? 'todos' : newStatus;
    setStatus(updated);
    applyFilters(updated);
  };

  const resetFilters = () => {
    setSearch('');
    setStatus('todos');
    setDeliveryMethod('todos');
    setPickupBranchId('all');
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
          status: status !== 'todos' ? status : undefined,
          delivery_method:
            deliveryMethod !== 'todos' ? deliveryMethod : undefined,
          pickup_branch_id:
            pickupBranchId !== 'all' ? pickupBranchId : undefined,
          date_from: dateFrom || undefined,
          date_to: dateTo || undefined,
        },
      }),
      {},
      { preserveState: true, preserveScroll: true },
    );
  };

  const handleStartPreparation = (order: Order) => {
    setIsSubmitting(true);
    router.post(
      startPreparation.url(order.id),
      {},
      {
        preserveScroll: true,
        onFinish: () => setIsSubmitting(false),
      },
    );
  };

  const handleConfirmDeliver = () => {
    if (!deliveringOrder) {
      return;
    }

    setIsSubmitting(true);
    router.post(
      deliver.url(deliveringOrder.id),
      {},
      {
        preserveScroll: true,
        onError: (errors) =>
          toast.error(
            errors.sale ??
              errors.warehouse ??
              errors.status ??
              'No se pudo confirmar la salida del pedido.',
          ),
        onFinish: () => {
          setIsSubmitting(false);
          setDeliveringOrder(null);
        },
      },
    );
  };

  const hasActiveFilters =
    search !== '' ||
    status !== 'todos' ||
    deliveryMethod !== 'todos' ||
    pickupBranchId !== 'all' ||
    dateFrom !== '' ||
    dateTo !== '';

  return (
    <>
      <Head title="Pedidos a preparar | Depósito" />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
          <Heading
            title="Pedidos a preparar"
            description="Bandeja de pedidos web para preparación física y despacho o entrega con baja de existencias."
          />
        </div>

        {/* Contadores por Estado (KPI Cards) */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <Card
            onClick={() => handleStatusCardClick('pagado')}
            className={`cursor-pointer transition-all hover:border-warning-fg/25 hover:shadow-xs ${
              status === 'pagado'
                ? 'border-warning-fg/25 bg-warning-bg/10 ring-2 ring-warning-fg/25'
                : 'bg-card'
            }`}
          >
            <CardContent className="flex items-center justify-between p-4">
              <div>
                <p className="text-xs font-medium text-muted-foreground">
                  Pagados por preparar
                </p>
                <p className="mt-1 text-2xl font-bold tracking-tight text-foreground">
                  {counts.paid}
                </p>
              </div>
              <div className="rounded-full bg-warning-bg p-2.5 text-warning-fg">
                <Clock className="size-5" />
              </div>
            </CardContent>
          </Card>

          <Card
            onClick={() => handleStatusCardClick('en_preparacion')}
            className={`cursor-pointer transition-all hover:border-info-fg/25 hover:shadow-xs ${
              status === 'en_preparacion'
                ? 'border-info-fg/25 bg-info-bg/10 ring-2 ring-info-fg/25'
                : 'bg-card'
            }`}
          >
            <CardContent className="flex items-center justify-between p-4">
              <div>
                <p className="text-xs font-medium text-muted-foreground">
                  En preparación
                </p>
                <p className="mt-1 text-2xl font-bold tracking-tight text-foreground">
                  {counts.in_preparation}
                </p>
              </div>
              <div className="rounded-full bg-info-bg p-2.5 text-info-fg">
                <Package className="size-5" />
              </div>
            </CardContent>
          </Card>

          <Card
            onClick={() => handleStatusCardClick('entregado')}
            className={`cursor-pointer transition-all hover:border-success-fg/25 hover:shadow-xs ${
              status === 'entregado'
                ? 'border-success-fg/25 bg-success-bg/10 ring-2 ring-success-fg/25'
                : 'bg-card'
            }`}
          >
            <CardContent className="flex items-center justify-between p-4">
              <div>
                <p className="text-xs font-medium text-muted-foreground">
                  Entregados / Despachados
                </p>
                <p className="mt-1 text-2xl font-bold tracking-tight text-foreground">
                  {counts.delivered}
                </p>
              </div>
              <div className="rounded-full bg-success-bg p-2.5 text-success-fg">
                <CheckCircle2 className="size-5" />
              </div>
            </CardContent>
          </Card>
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
                    placeholder="N.º pedido o venta, cliente o dirección..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    onKeyDown={handleKeyDown}
                    className="pl-9"
                  />
                </div>
              </div>

              <div className="space-y-1.5 sm:col-span-1 md:col-span-1 lg:col-span-2">
                <Label>Estado</Label>
                <Select
                  value={status}
                  onValueChange={(val) => {
                    setStatus(val);
                  }}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder="Estado" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="todos">Todos los activos</SelectItem>
                    <SelectItem value="pagado">Pagados por preparar</SelectItem>
                    <SelectItem value="en_preparacion">
                      En preparación
                    </SelectItem>
                    <SelectItem value="entregado">
                      Entregados / Despachados
                    </SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-1.5 sm:col-span-1 md:col-span-1 lg:col-span-2">
                <Label>Modalidad</Label>
                <Select
                  value={deliveryMethod}
                  onValueChange={(val) => {
                    setDeliveryMethod(val);
                  }}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder="Modalidad" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="todos">Todas</SelectItem>
                    <SelectItem value="retiro">Retiro en sucursal</SelectItem>
                    <SelectItem value="envio">Envío a domicilio</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-1.5 sm:col-span-1 md:col-span-1 lg:col-span-2">
                <Label>Sucursal de retiro</Label>
                <Select
                  value={pickupBranchId}
                  onValueChange={(val) => {
                    setPickupBranchId(val);
                  }}
                >
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder="Sucursal" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todas</SelectItem>
                    {branches.map((b) => (
                      <SelectItem key={b.id} value={String(b.id)}>
                        {b.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-1.5 sm:col-span-1 md:col-span-1 lg:col-span-1">
                <Label htmlFor="date_from">Desde</Label>
                <Input
                  id="date_from"
                  type="date"
                  value={dateFrom}
                  onChange={(e) => setDateFrom(e.target.value)}
                />
              </div>

              <div className="space-y-1.5 sm:col-span-1 md:col-span-1 lg:col-span-1">
                <Label htmlFor="date_to">Hasta</Label>
                <Input
                  id="date_to"
                  type="date"
                  value={dateTo}
                  onChange={(e) => setDateTo(e.target.value)}
                />
              </div>
            </div>

            <div className="mt-4 flex flex-wrap items-center justify-end gap-2 border-t pt-3">
              {hasActiveFilters && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={resetFilters}
                  className="gap-1.5"
                >
                  <RotateCcw className="size-3.5" />
                  Limpiar filtros
                </Button>
              )}
              <Button
                size="sm"
                onClick={() => applyFilters()}
                className="gap-1.5"
              >
                <Filter className="size-3.5" />
                Aplicar filtros
              </Button>
            </div>
          </CardContent>
        </Card>

        {/* Tabla de Pedidos */}
        <Card className="border bg-card shadow-xs">
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-[110px]">N.º Pedido</TableHead>
                  <TableHead>Fecha pago</TableHead>
                  <TableHead>Cliente</TableHead>
                  <TableHead>Modalidad / Destino</TableHead>
                  <TableHead className="w-[140px]">Estado</TableHead>
                  <TableHead className="text-center">Artículos</TableHead>
                  <TableHead className="text-right">Total</TableHead>
                  <TableHead className="w-[200px] text-right">
                    Acciones
                  </TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {orders.data.length === 0 ? (
                  <TableRow>
                    <TableCell
                      colSpan={8}
                      className="py-12 text-center text-muted-foreground"
                    >
                      <Package className="mx-auto mb-2 size-8 text-muted-foreground/60" />
                      <p className="font-medium">
                        No se encontraron pedidos web
                      </p>
                      <p className="mt-1 text-xs">
                        Ajustá los filtros o el término de búsqueda para ver más
                        resultados.
                      </p>
                    </TableCell>
                  </TableRow>
                ) : (
                  orders.data.map((order) => (
                    <TableRow key={order.id} className="hover:bg-muted/50">
                      <TableCell className="font-mono font-medium">
                        <Link
                          href={show.url(order.id)}
                          className="text-primary hover:underline"
                        >
                          #{order.formatted_number}
                        </Link>
                        {order.sale_id && (
                          <div className="font-sans text-[11px] font-normal text-muted-foreground">
                            Venta #{order.sale_id}
                          </div>
                        )}
                      </TableCell>
                      <TableCell className="text-xs text-muted-foreground">
                        {order.paid_at_formatted ?? order.placed_at_formatted}
                      </TableCell>
                      <TableCell>
                        <div className="font-medium text-foreground">
                          {order.customer_name}
                        </div>
                        {order.customer_document && (
                          <div className="text-xs text-muted-foreground">
                            {order.customer_document}
                          </div>
                        )}
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center gap-1.5">
                          <Badge
                            variant="outline"
                            className="text-xs font-normal"
                          >
                            {order.delivery_method === 'envio' ? (
                              <Truck className="mr-1 size-3 text-primary" />
                            ) : (
                              <Package className="mr-1 size-3 text-primary" />
                            )}
                            {order.delivery_method_label}
                          </Badge>
                        </div>
                        <div
                          className="mt-1 max-w-[220px] truncate text-xs text-muted-foreground"
                          title={order.destination}
                        >
                          {order.destination}
                        </div>
                      </TableCell>
                      <TableCell>
                        <Badge
                          className={`text-xs font-medium shadow-none ${
                            statusBadgeClasses[order.status] ?? ''
                          }`}
                        >
                          {order.status_label}
                        </Badge>
                        {order.prepared_by_name && (
                          <div className="mt-1 text-[11px] text-muted-foreground">
                            Prep: {order.prepared_by_name}
                          </div>
                        )}
                        {order.delivered_by_name && (
                          <div className="text-[11px] text-muted-foreground">
                            Entr: {order.delivered_by_name}
                          </div>
                        )}
                      </TableCell>
                      <TableCell className="text-center font-medium">
                        {order.items_count}
                      </TableCell>
                      <TableCell className="text-right font-medium text-foreground">
                        {order.formatted_total_amount}
                      </TableCell>
                      <TableCell className="text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="size-8 p-0"
                            title="Ver hoja de preparación"
                          >
                            <Link href={show.url(order.id)}>
                              <Eye className="size-4" />
                            </Link>
                          </Button>

                          {order.can_start_preparation && (
                            <Button
                              size="sm"
                              variant="outline"
                              onClick={() => handleStartPreparation(order)}
                              disabled={isSubmitting}
                              className="gap-1 border-info-fg/25 text-xs font-medium text-foreground hover:bg-info-bg hover:text-info-fg"
                            >
                              <Package className="size-3.5" />
                              Preparar
                            </Button>
                          )}

                          {order.can_deliver && (
                            <Button
                              size="sm"
                              onClick={() => setDeliveringOrder(order)}
                              disabled={isSubmitting}
                              className="gap-1 bg-primary text-xs font-medium text-primary-foreground hover:bg-primary/90"
                            >
                              <CheckCircle2 className="size-3.5" />
                              {order.delivery_method === 'envio'
                                ? 'Despachar'
                                : 'Entregar'}
                            </Button>
                          )}
                        </div>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        {/* Paginación */}
        <TablePagination
          currentPage={orders.current_page}
          totalPages={orders.last_page}
          totalItems={orders.total}
          pageSize={orders.per_page}
          onPageChange={changePage}
          entityName="pedidos web"
        />

        {/* Modal de confirmación de entrega / despacho */}
        <Dialog
          open={deliveringOrder !== null}
          onOpenChange={(open) => {
            if (!open) {
              setDeliveringOrder(null);
            }
          }}
        >
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle className="flex items-center gap-2 text-foreground">
                <CheckCircle2 className="size-5 text-primary" />
                Confirmar{' '}
                {deliveringOrder?.delivery_method === 'envio'
                  ? 'despacho'
                  : 'entrega'}{' '}
                de pedido #{deliveringOrder?.formatted_number}
              </DialogTitle>
              <DialogDescription>
                Se registrará la salida definitiva de la mercadería del
                depósito.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-3 py-2 text-sm">
              <div className="rounded-md border border-warning-fg/25 bg-warning-bg p-3 text-warning-fg">
                <div className="flex items-start gap-2">
                  <AlertTriangle className="mt-0.5 size-4 shrink-0 text-warning-fg" />
                  <div className="text-xs">
                    <p className="font-semibold">Baja de stock automática:</p>
                    <p className="mt-0.5">
                      Al confirmar, se creará un movimiento inmutable de{' '}
                      <strong>Salida por Entrega de Pedido Web</strong> y se
                      descontará el stock de los artículos en el depósito
                      online.
                    </p>
                  </div>
                </div>
              </div>

              {deliveringOrder && (
                <div className="space-y-1 rounded-md bg-muted p-3 text-xs">
                  <p>
                    <span className="font-medium text-foreground">
                      Cliente:
                    </span>{' '}
                    {deliveringOrder.customer_name}
                  </p>
                  <p>
                    <span className="font-medium text-foreground">
                      Modalidad:
                    </span>{' '}
                    {deliveringOrder.delivery_method_label}
                  </p>
                  <p>
                    <span className="font-medium text-foreground">
                      Destino:
                    </span>{' '}
                    {deliveringOrder.destination}
                  </p>
                  <p>
                    <span className="font-medium text-foreground">
                      Total artículos:
                    </span>{' '}
                    {deliveringOrder.items_count}
                  </p>
                </div>
              )}
            </div>

            <DialogFooter className="gap-2 sm:gap-0">
              <Button
                variant="outline"
                onClick={() => setDeliveringOrder(null)}
                disabled={isSubmitting}
              >
                Cancelar
              </Button>
              <Button
                onClick={handleConfirmDeliver}
                disabled={isSubmitting}
                className="bg-primary font-medium text-primary-foreground hover:bg-primary/90"
              >
                {isSubmitting
                  ? 'Confirmando...'
                  : deliveringOrder?.delivery_method === 'envio'
                    ? 'Confirmar despacho'
                    : 'Confirmar entrega'}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </>
  );
}
