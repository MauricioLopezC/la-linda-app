import { Head, Link, router } from '@inertiajs/react';
import {
  CalendarClock,
  Eye,
  Filter,
  Plus,
  ReceiptText,
  RotateCcw,
  Search,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import TablePagination from '@/components/table-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { create, index, show } from '@/routes/purchasing/vouchers';
import type { BreadcrumbItem } from '@/types';

type Voucher = App.Data.Purchasing.SupplierVoucherListData;
type Supplier = App.Data.Purchasing.SupplierOptionData;
type Option = App.Data.Purchasing.SupplierVoucherOptionData;

type Props = {
  vouchers: {
    data: Voucher[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  suppliers: Supplier[];
  voucherTypes: Option[];
  statuses: Option[];
  filters: {
    search: string;
    supplier_id: string;
    type: string;
    status: string;
    date_from: string;
    date_to: string;
    only_overdue: boolean;
  };
};

const statusClasses: Record<string, string> = {
  pendiente: 'border-warning-fg/30 bg-warning-bg text-warning-fg',
  pagada_parcial: 'border-info-fg/30 bg-info-bg text-info-fg',
  pagada: 'border-success-fg/30 bg-success-bg text-success-fg',
  pendiente_imputar: 'border-info-fg/30 bg-info-bg text-info-fg',
  imputada_parcial: 'border-info-fg/30 bg-info-bg text-info-fg',
  imputada: 'border-success-fg/30 bg-success-bg text-success-fg',
  anulada: 'border-error-fg/30 bg-error-bg text-error-fg',
};

export default function SupplierVouchersIndex({
  vouchers,
  suppliers,
  voucherTypes,
  statuses,
  filters,
}: Props) {
  const [search, setSearch] = useState(filters.search);
  const [supplierId, setSupplierId] = useState(filters.supplier_id || 'all');
  const [type, setType] = useState(filters.type || 'all');
  const [status, setStatus] = useState(filters.status || 'all');
  const [dateFrom, setDateFrom] = useState(filters.date_from);
  const [dateTo, setDateTo] = useState(filters.date_to);
  const [onlyOverdue, setOnlyOverdue] = useState(filters.only_overdue);

  const query = (page?: number) => ({
    page,
    search: search || undefined,
    supplier_id: supplierId === 'all' ? undefined : supplierId,
    type: type === 'all' ? undefined : type,
    status: status === 'all' ? undefined : status,
    date_from: dateFrom || undefined,
    date_to: dateTo || undefined,
    only_overdue: onlyOverdue ? 1 : undefined,
  });

  const applyFilters = () =>
    router.get(
      index.url({ query: query() }),
      {},
      { preserveState: true, preserveScroll: true },
    );
  const resetFilters = () => {
    setSearch('');
    setSupplierId('all');
    setType('all');
    setStatus('all');
    setDateFrom('');
    setDateTo('');
    setOnlyOverdue(false);
    router.get(index.url(), {}, { preserveState: true, preserveScroll: true });
  };

  return (
    <>
      <Head title="Comprobantes de proveedores" />
      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <Heading
            title="Comprobantes de proveedores"
            description="Consultá facturas y notas, su vencimiento y el saldo con cada proveedor."
          />
          <Button asChild>
            <Link href={create()}>
              <Plus className="size-4" />
              Nuevo comprobante
            </Link>
          </Button>
        </div>

        <Card>
          <CardContent className="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-12">
            <div className="space-y-1.5 md:col-span-2 xl:col-span-4">
              <Label htmlFor="search">Buscar</Label>
              <div className="relative">
                <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                <Input
                  id="search"
                  className="pl-8"
                  placeholder="Proveedor o 0001-00000001"
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
              label="Tipo"
              value={type}
              onChange={setType}
              options={voucherTypes}
            />
            <FilterSelect
              label="Estado"
              value={status}
              onChange={setStatus}
              options={statuses}
            />
            <div className="space-y-1.5 xl:col-span-2">
              <Label htmlFor="date_from">Emisión desde</Label>
              <Input
                id="date_from"
                type="date"
                value={dateFrom}
                onChange={(event) => setDateFrom(event.target.value)}
              />
            </div>
            <div className="space-y-1.5 xl:col-span-2">
              <Label htmlFor="date_to">Emisión hasta</Label>
              <Input
                id="date_to"
                type="date"
                min={dateFrom}
                value={dateTo}
                onChange={(event) => setDateTo(event.target.value)}
              />
            </div>
            <div className="flex items-end gap-2 md:col-span-2 xl:col-span-12 xl:justify-end">
              <label className="mr-auto flex items-center gap-2 text-sm">
                <Checkbox
                  checked={onlyOverdue}
                  onCheckedChange={(checked) =>
                    setOnlyOverdue(checked === true)
                  }
                />
                Solo vencidos
              </label>
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

        <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Proveedor</TableHead>
                <TableHead>Comprobante</TableHead>
                <TableHead>Fechas</TableHead>
                <TableHead className="text-right">Total</TableHead>
                <TableHead className="text-right">Saldo</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {vouchers.data.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={7} className="py-14 text-center">
                    <div className="flex flex-col items-center gap-2 text-muted-foreground">
                      <ReceiptText className="size-8" />
                      <span>No hay comprobantes para la búsqueda.</span>
                    </div>
                  </TableCell>
                </TableRow>
              ) : (
                vouchers.data.map((voucher) => (
                  <TableRow key={voucher.id}>
                    <TableCell className="font-medium">
                      {voucher.supplier_business_name}
                    </TableCell>
                    <TableCell>
                      <div className="flex flex-col gap-1">
                        <span>{voucher.type_label}</span>
                        <span className="font-mono text-xs text-muted-foreground">
                          {voucher.formatted_number}
                        </span>
                      </div>
                    </TableCell>
                    <TableCell>
                      <div className="flex flex-col gap-1 text-sm">
                        <span>Emisión: {voucher.issue_date_formatted}</span>
                        <span className="text-muted-foreground">
                          Vencimiento: {voucher.due_date_formatted ?? '—'}
                        </span>
                        {voucher.is_overdue && (
                          <Badge
                            variant="outline"
                            className="w-fit border-error-fg/30 bg-error-bg text-error-fg"
                          >
                            <CalendarClock className="size-3" />
                            Vencido
                          </Badge>
                        )}
                      </div>
                    </TableCell>
                    <TableCell className="text-right font-mono">
                      {formatCurrency(voucher.total_amount)}
                    </TableCell>
                    <TableCell className="text-right font-mono font-semibold">
                      {formatCurrency(voucher.outstanding_amount)}
                    </TableCell>
                    <TableCell>
                      <Badge
                        variant="outline"
                        className={statusClasses[voucher.status] ?? ''}
                      >
                        {voucher.status_label}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <Button variant="ghost" size="sm" asChild>
                        <Link href={show(voucher.id)}>
                          <Eye className="size-4" />
                          Ver
                        </Link>
                      </Button>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
          <TablePagination
            currentPage={vouchers.current_page}
            totalPages={vouchers.last_page}
            totalItems={vouchers.total}
            pageSize={vouchers.per_page}
            onPageChange={(page) =>
              router.get(
                index.url({ query: query(page) }),
                {},
                { preserveState: true, preserveScroll: true },
              )
            }
            entityName="comprobantes"
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

SupplierVouchersIndex.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Comprobantes de proveedores', href: index() },
  ] satisfies BreadcrumbItem[],
};
