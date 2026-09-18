import { Head, Link, router } from '@inertiajs/react';
import {
  ChevronDown,
  ChevronRight,
  Download,
  FileSpreadsheet,
  FileText,
  Filter,
  Plus,
  Receipt,
  RotateCcw,
  Search,
  Wallet,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import { pdf } from '@/actions/App/Http/Controllers/Purchasing/PaymentOrderController';
import Heading from '@/components/heading';
import TablePagination from '@/components/table-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { create, index } from '@/routes/purchasing/payment-orders';
import { csv, excel } from '@/routes/purchasing/payment-orders/export';
import type { BreadcrumbItem } from '@/types';

type PaymentOrder = App.Data.Purchasing.PaymentOrderListData;
type SupplierOption = App.Data.Purchasing.SupplierOptionData;
type PaymentMethodOption = App.Data.Sales.PaymentMethodData;
type Option = App.Data.Purchasing.SupplierVoucherOptionData;

type Props = {
  orders: {
    data: PaymentOrder[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  suppliers: SupplierOption[];
  paymentMethods: PaymentMethodOption[];
  voucherTypes: Option[];
  statuses: Option[];
  totalEgresses: string;
  filters: {
    search: string;
    supplier_id: string;
    payment_method_id: string;
    voucher_type: string;
    status: string;
    date_from: string;
    date_to: string;
  };
};

const statusClasses: Record<string, string> = {
  emitida: 'border-success-fg/30 bg-success-bg text-success-fg',
  anulada: 'border-error-fg/30 bg-error-bg text-error-fg',
};

export default function PaymentOrdersIndex({
  orders,
  suppliers,
  paymentMethods,
  voucherTypes,
  statuses,
  totalEgresses,
  filters,
}: Props) {
  const [search, setSearch] = useState(filters.search);
  const [supplierId, setSupplierId] = useState(filters.supplier_id || 'all');
  const [paymentMethodId, setPaymentMethodId] = useState(
    filters.payment_method_id || 'all',
  );
  const [voucherType, setVoucherType] = useState(filters.voucher_type || 'all');
  const [status, setStatus] = useState(filters.status || 'all');
  const [dateFrom, setDateFrom] = useState(filters.date_from);
  const [dateTo, setDateTo] = useState(filters.date_to);

  // Expanded rows state
  const [expandedOrders, setExpandedOrders] = useState<Record<number, boolean>>(
    {},
  );

  const toggleExpand = (orderId: number) => {
    setExpandedOrders((prev) => ({
      ...prev,
      [orderId]: !prev[orderId],
    }));
  };

  const queryParams = (page?: number) => ({
    page,
    search: search || undefined,
    supplier_id: supplierId === 'all' ? undefined : supplierId,
    payment_method_id: paymentMethodId === 'all' ? undefined : paymentMethodId,
    voucher_type: voucherType === 'all' ? undefined : voucherType,
    status: status === 'all' ? undefined : status,
    date_from: dateFrom || undefined,
    date_to: dateTo || undefined,
  });

  const applyFilters = () =>
    router.get(
      index.url({ query: queryParams() }),
      {},
      { preserveState: true, preserveScroll: true },
    );

  const resetFilters = () => {
    setSearch('');
    setSupplierId('all');
    setPaymentMethodId('all');
    setVoucherType('all');
    setStatus('all');
    setDateFrom('');
    setDateTo('');
    router.get(index.url(), {}, { preserveState: true, preserveScroll: true });
  };

  return (
    <>
      <Head title="Pagos y egresos del período" />
      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        {/* Header & Main Actions */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <Heading
            title="Pagos y egresos del período"
            description="Consultá las órdenes de pago, comprobantes imputados y total de egresos realizados."
          />
          <div className="flex items-center gap-2">
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" className="gap-1.5">
                  <Download className="size-4" />
                  Exportar
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end">
                <DropdownMenuItem asChild>
                  <a
                    href={csv.url({ query: queryParams() })}
                    download
                    className="flex cursor-pointer items-center gap-2"
                  >
                    <FileText className="size-4 text-muted-foreground" />
                    Exportar a CSV
                  </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                  <a
                    href={excel.url({ query: queryParams() })}
                    download
                    className="flex cursor-pointer items-center gap-2"
                  >
                    <FileSpreadsheet className="size-4 text-muted-foreground" />
                    Exportar a Excel
                  </a>
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            <Button asChild>
              <Link href={create()}>
                <Plus className="size-4" />
                Nueva orden de pago
              </Link>
            </Button>
          </div>
        </div>

        {/* Egresses Total Card */}
        <div className="grid gap-4 md:grid-cols-3">
          <Card className="border-primary/20 bg-primary-50/40 dark:bg-primary-900/10">
            <CardContent className="flex items-center justify-between p-5">
              <div className="space-y-1">
                <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                  Total de Egresos del Período
                </p>
                <p className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
                  {formatCurrency(totalEgresses)}
                </p>
                <p className="text-xs text-muted-foreground">
                  Suma de órdenes de pago imputadas en el período seleccionado
                  (excluye órdenes anuladas y comprobantes impagos).
                </p>
              </div>
              <div className="rounded-xl border border-primary/20 bg-card p-3 text-primary shadow-xs">
                <Wallet className="size-7" />
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Filter Card */}
        <Card>
          <CardContent className="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-12">
            <div className="space-y-1.5 md:col-span-2 xl:col-span-3">
              <Label htmlFor="search">Buscar</Label>
              <div className="relative">
                <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                <Input
                  id="search"
                  className="pl-8"
                  placeholder="N° orden (OP-000001) o proveedor"
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                  onKeyDown={(event) => event.key === 'Enter' && applyFilters()}
                />
              </div>
            </div>

            <FilterSelect
              label="Proveedor"
              value={supplierId}
              onChange={setSupplierId}
              options={suppliers.map((supplier) => ({
                value: String(supplier.id),
                label: supplier.business_name,
              }))}
            />

            <FilterSelect
              label="Tipo comprobante"
              value={voucherType}
              onChange={setVoucherType}
              options={voucherTypes}
            />

            <FilterSelect
              label="Medio de pago"
              value={paymentMethodId}
              onChange={setPaymentMethodId}
              options={paymentMethods.map((method) => ({
                value: String(method.id),
                label: method.name,
              }))}
            />

            <FilterSelect
              label="Estado"
              value={status}
              onChange={setStatus}
              options={statuses}
            />

            <div className="space-y-1.5 xl:col-span-2">
              <Label htmlFor="date_from">Fecha desde</Label>
              <Input
                id="date_from"
                type="date"
                value={dateFrom}
                onChange={(event) => setDateFrom(event.target.value)}
              />
            </div>

            <div className="space-y-1.5 xl:col-span-2">
              <Label htmlFor="date_to">Fecha hasta</Label>
              <Input
                id="date_to"
                type="date"
                min={dateFrom}
                value={dateTo}
                onChange={(event) => setDateTo(event.target.value)}
              />
            </div>

            <div className="flex items-end gap-2 md:col-span-2 xl:col-span-12 xl:justify-end">
              <Button onClick={applyFilters}>
                <Filter className="size-4" />
                Filtrar
              </Button>
              <Button
                variant="outline"
                size="icon"
                onClick={resetFilters}
                aria-label="Limpiar filtros"
              >
                <RotateCcw className="size-4" />
              </Button>
            </div>
          </CardContent>
        </Card>

        {/* Orders Table */}
        <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-10"></TableHead>
                <TableHead>N° Orden</TableHead>
                <TableHead>Fecha</TableHead>
                <TableHead>Proveedor</TableHead>
                <TableHead>Medios de Pago</TableHead>
                <TableHead className="text-right">Importe Total</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {orders.data.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={8} className="py-14 text-center">
                    <div className="flex flex-col items-center gap-2 text-muted-foreground">
                      <Receipt className="size-8" />
                      <span>
                        No se encontraron órdenes de pago para los filtros
                        seleccionados.
                      </span>
                    </div>
                  </TableCell>
                </TableRow>
              ) : (
                orders.data.map((order) => {
                  const isExpanded = !!expandedOrders[order.id];

                  return (
                    <Fragment key={order.id}>
                      <TableRow className="transition-colors hover:bg-muted/40">
                        <TableCell className="w-10 pl-4">
                          <button
                            type="button"
                            onClick={() => toggleExpand(order.id)}
                            className="inline-flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground"
                            aria-label={
                              isExpanded
                                ? 'Ocultar comprobantes'
                                : 'Ver comprobantes afectados'
                            }
                          >
                            {isExpanded ? (
                              <ChevronDown className="size-4 text-primary" />
                            ) : (
                              <ChevronRight className="size-4" />
                            )}
                          </button>
                        </TableCell>
                        <TableCell className="font-mono font-semibold">
                          {order.order_number}
                        </TableCell>
                        <TableCell>{order.date_formatted}</TableCell>
                        <TableCell className="font-medium">
                          {order.supplier_name}
                        </TableCell>
                        <TableCell className="text-sm text-muted-foreground">
                          {order.payment_methods_summary}
                        </TableCell>
                        <TableCell className="text-right font-mono font-semibold">
                          {formatCurrency(order.total_amount)}
                        </TableCell>
                        <TableCell>
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
                              size="sm"
                              onClick={() => toggleExpand(order.id)}
                              className="text-xs"
                            >
                              {isExpanded ? 'Ocultar' : 'Detalle'}
                            </Button>
                            <Button variant="ghost" size="icon" asChild>
                              <a
                                href={pdf.url(order.id)}
                                target="_blank"
                                rel="noreferrer"
                                title="Descargar PDF"
                              >
                                <FileText className="size-4 text-muted-foreground" />
                              </a>
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>

                      {/* Expanded Details Row */}
                      {isExpanded && (
                        <TableRow className="bg-muted/20 hover:bg-muted/20">
                          <TableCell colSpan={8} className="p-4 pl-12">
                            <div className="space-y-3 rounded-lg border border-border bg-card p-4 shadow-2xs">
                              <h4 className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                Comprobantes afectados por esta orden de pago (
                                {order.items.length})
                              </h4>
                              {order.items.length === 0 ? (
                                <p className="text-sm text-muted-foreground italic">
                                  No hay comprobantes asociados registrados.
                                </p>
                              ) : (
                                <div className="overflow-hidden rounded-md border border-border">
                                  <Table>
                                    <TableHeader className="bg-muted/40 text-xs">
                                      <TableRow>
                                        <TableHead>Tipo</TableHead>
                                        <TableHead>Comprobante</TableHead>
                                        <TableHead className="text-right">
                                          Importe Imputado
                                        </TableHead>
                                      </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                      {order.items.map((item) => (
                                        <TableRow key={item.id}>
                                          <TableCell className="text-sm">
                                            {item.voucher_type_label}
                                          </TableCell>
                                          <TableCell className="font-mono text-sm">
                                            {item.voucher_number}
                                          </TableCell>
                                          <TableCell className="text-right font-mono text-sm font-medium">
                                            {formatCurrency(
                                              item.amount_applied,
                                            )}
                                          </TableCell>
                                        </TableRow>
                                      ))}
                                    </TableBody>
                                  </Table>
                                </div>
                              )}

                              {/* Payment Methods Breakdown */}
                              {order.methods.length > 0 && (
                                <div className="pt-2">
                                  <h4 className="mb-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    Desglose de medios de pago
                                  </h4>
                                  <div className="flex flex-wrap gap-2">
                                    {order.methods.map((method) => (
                                      <div
                                        key={method.id}
                                        className="inline-flex items-center gap-1.5 rounded-md border border-border bg-background px-2.5 py-1 text-xs"
                                      >
                                        <span className="font-medium">
                                          {method.payment_method_name}:
                                        </span>
                                        <span className="font-mono">
                                          {formatCurrency(method.amount)}
                                        </span>
                                        {method.reference && (
                                          <span className="text-muted-foreground">
                                            ({method.reference})
                                          </span>
                                        )}
                                      </div>
                                    ))}
                                  </div>
                                </div>
                              )}
                            </div>
                          </TableCell>
                        </TableRow>
                      )}
                    </Fragment>
                  );
                })
              )}
            </TableBody>
          </Table>
          <TablePagination
            currentPage={orders.current_page}
            totalPages={orders.last_page}
            totalItems={orders.total}
            pageSize={orders.per_page}
            onPageChange={(page) =>
              router.get(
                index.url({ query: queryParams(page) }),
                {},
                { preserveState: true, preserveScroll: true },
              )
            }
            entityName="órdenes de pago"
          />
        </div>
      </div>
    </>
  );
}

function FilterSelect({
  label,
  value,
  onChange,
  options,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  options: Array<{ value: string; label: string }>;
}) {
  return (
    <div className="space-y-1.5 xl:col-span-2">
      <Label>{label}</Label>
      <Select value={value} onValueChange={onChange}>
        <SelectTrigger className="w-full">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">Todos</SelectItem>
          {options.map((option) => (
            <SelectItem key={option.value} value={option.value}>
              {option.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );
}

PaymentOrdersIndex.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Pagos y egresos', href: index() },
  ] satisfies BreadcrumbItem[],
};
