import { Head, Link, router } from '@inertiajs/react';
import {
  CalendarClock,
  Download,
  Eye,
  FileSpreadsheet,
  FileText,
  Filter,
  RotateCcw,
  Scale,
  Wallet,
  Wallet2,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import accountStatement from '@/routes/purchasing/account-statement';
import { csv, excel } from '@/routes/purchasing/account-statement/export';
import { show } from '@/routes/purchasing/vouchers';
import type { BreadcrumbItem } from '@/types';

type Supplier = App.Data.Purchasing.SupplierOptionData;
type Totals = App.Data.Purchasing.SupplierAccountStatementTotalsData;
type Item = App.Data.Purchasing.SupplierAccountStatementItemData;

type Props = {
  suppliers: Supplier[];
  totals: Totals | null;
  items: Item[];
  filters: {
    supplier_id: string;
    date_from: string;
    date_to: string;
  };
};

export default function SupplierAccountStatementIndex({
  suppliers,
  totals,
  items,
  filters,
}: Props) {
  const [supplierId, setSupplierId] = useState(filters.supplier_id);
  const [dateFrom, setDateFrom] = useState(filters.date_from);
  const [dateTo, setDateTo] = useState(filters.date_to);

  const query = () => ({
    supplier_id: supplierId || undefined,
    date_from: dateFrom || undefined,
    date_to: dateTo || undefined,
  });

  const applyFilters = () =>
    router.get(
      accountStatement.index.url({ query: query() }),
      {},
      { preserveState: true, preserveScroll: true },
    );

  const resetFilters = () => {
    setSupplierId('');
    setDateFrom('');
    setDateTo('');
    router.get(
      accountStatement.index.url(),
      {},
      { preserveState: true, preserveScroll: true },
    );
  };

  return (
    <>
      <Head title="Cuenta corriente de proveedores" />
      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <Heading
          title="Cuenta corriente de proveedores"
          description="Consultá cuánto se le compró y se le pagó a un proveedor, y el detalle de lo que todavía está pendiente."
        />

        <Card>
          <CardContent className="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-12">
            <div className="space-y-1.5 xl:col-span-5">
              <Label>Proveedor</Label>
              <Select
                value={supplierId || undefined}
                onValueChange={setSupplierId}
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Seleccioná un proveedor" />
                </SelectTrigger>
                <SelectContent>
                  {suppliers.map((supplier) => (
                    <SelectItem key={supplier.id} value={String(supplier.id)}>
                      {supplier.business_name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5 xl:col-span-3">
              <Label htmlFor="date_from">Emisión desde</Label>
              <Input
                id="date_from"
                type="date"
                value={dateFrom}
                onChange={(event) => setDateFrom(event.target.value)}
              />
            </div>
            <div className="space-y-1.5 xl:col-span-3">
              <Label htmlFor="date_to">Emisión hasta</Label>
              <Input
                id="date_to"
                type="date"
                min={dateFrom}
                value={dateTo}
                onChange={(event) => setDateTo(event.target.value)}
              />
            </div>
            <div className="flex items-end gap-2 md:col-span-2 xl:col-span-1">
              <Button onClick={applyFilters} disabled={!supplierId}>
                <Filter className="size-4" />
                Consultar
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

        {totals === null ? (
          <div className="flex flex-col items-center gap-2 rounded-xl border border-dashed border-sidebar-border p-14 text-center text-muted-foreground">
            <Wallet2 className="size-8" />
            <span>
              Seleccioná un proveedor para consultar su cuenta corriente.
            </span>
          </div>
        ) : (
          <>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <Card>
                <CardHeader className="flex flex-row items-center justify-between pb-2">
                  <CardTitle className="text-sm font-medium text-muted-foreground">
                    Total recibido
                  </CardTitle>
                  <Wallet className="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold text-foreground">
                    {formatCurrency(totals.total_received)}
                  </div>
                </CardContent>
              </Card>
              <Card>
                <CardHeader className="flex flex-row items-center justify-between pb-2">
                  <CardTitle className="text-sm font-medium text-muted-foreground">
                    Total pagado
                  </CardTitle>
                  <Wallet2 className="size-4 text-emerald-600 dark:text-emerald-400" />
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                    {formatCurrency(totals.total_paid)}
                  </div>
                </CardContent>
              </Card>
              <Card>
                <CardHeader className="flex flex-row items-center justify-between pb-2">
                  <CardTitle className="text-sm font-medium text-muted-foreground">
                    Saldo
                  </CardTitle>
                  <Scale className="size-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold text-foreground">
                    {formatCurrency(totals.balance)}
                  </div>
                </CardContent>
              </Card>
            </div>

            <div className="flex items-center justify-between">
              <h2 className="text-sm font-semibold text-muted-foreground">
                Comprobantes pendientes
              </h2>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" size="sm" className="gap-1.5">
                    <Download className="size-4" />
                    Exportar
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem asChild>
                    <a
                      href={csv.url({ query: query() })}
                      download
                      className="flex cursor-pointer items-center gap-2"
                    >
                      <FileText className="size-4 text-muted-foreground" />
                      Exportar a CSV
                    </a>
                  </DropdownMenuItem>
                  <DropdownMenuItem asChild>
                    <a
                      href={excel.url({ query: query() })}
                      download
                      className="flex cursor-pointer items-center gap-2"
                    >
                      <FileSpreadsheet className="size-4 text-muted-foreground" />
                      Exportar a Excel
                    </a>
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>

            <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Comprobante</TableHead>
                    <TableHead>Fechas</TableHead>
                    <TableHead className="text-right">Importe</TableHead>
                    <TableHead className="text-right">Pagado</TableHead>
                    <TableHead className="text-right">Saldo</TableHead>
                    <TableHead className="text-right">Antigüedad</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {items.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={7} className="py-14 text-center">
                        <div className="flex flex-col items-center gap-2 text-muted-foreground">
                          <Wallet2 className="size-8" />
                          <span>
                            Este proveedor no tiene comprobantes pendientes.
                          </span>
                        </div>
                      </TableCell>
                    </TableRow>
                  ) : (
                    items.map((item) => (
                      <TableRow key={item.id}>
                        <TableCell>
                          <div className="flex flex-col gap-1">
                            <span>{item.type_label}</span>
                            <span className="font-mono text-xs text-muted-foreground">
                              {item.formatted_number}
                            </span>
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex flex-col gap-1 text-sm">
                            <span>Emisión: {item.issue_date_formatted}</span>
                            <span className="text-muted-foreground">
                              Vencimiento: {item.due_date_formatted ?? '—'}
                            </span>
                            {item.is_overdue && (
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
                          {formatCurrency(item.total_amount)}
                        </TableCell>
                        <TableCell className="text-right font-mono">
                          {formatCurrency(item.paid_amount)}
                        </TableCell>
                        <TableCell className="text-right font-mono font-semibold">
                          {formatCurrency(item.balance)}
                        </TableCell>
                        <TableCell className="text-right">
                          {item.aging_days} días
                        </TableCell>
                        <TableCell className="text-right">
                          <Button variant="ghost" size="sm" asChild>
                            <Link href={show(item.id)}>
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
            </div>
          </>
        )}
      </div>
    </>
  );
}

SupplierAccountStatementIndex.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    {
      title: 'Cuenta corriente de proveedores',
      href: accountStatement.index(),
    },
  ] satisfies BreadcrumbItem[],
};
