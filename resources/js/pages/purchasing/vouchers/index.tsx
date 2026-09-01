import { Head, Link, router } from '@inertiajs/react';
import { CalendarClock, Download, Plus, ReceiptText } from 'lucide-react';
import Heading from '@/components/heading';
import TablePagination from '@/components/table-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { create, index, pdf } from '@/routes/purchasing/vouchers';
import type { BreadcrumbItem } from '@/types';

type Voucher = App.Data.Purchasing.SupplierVoucherListData;

type VoucherPage = {
  data: Voucher[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

type Props = {
  vouchers: VoucherPage;
};

const statusClasses: Record<string, string> = {
  pendiente: 'border-warning-fg/30 bg-warning-bg text-warning-fg',
  pagada_parcial: 'border-info-fg/30 bg-info-bg text-info-fg',
  pagada: 'border-success-fg/30 bg-success-bg text-success-fg',
  pendiente_imputar: 'border-warning-fg/30 bg-warning-bg text-warning-fg',
  imputada_parcial: 'border-info-fg/30 bg-info-bg text-info-fg',
  imputada: 'border-success-fg/30 bg-success-bg text-success-fg',
  anulada: 'border-error-fg/30 bg-error-bg text-error-fg',
};

export default function SupplierVouchersIndex({ vouchers }: Props) {
  const changePage = (page: number) => {
    router.get(
      index.url({ query: { page } }),
      {},
      { preserveScroll: true, preserveState: true },
    );
  };

  return (
    <>
      <Head title="Comprobantes de proveedores" />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <Heading
            title="Comprobantes de proveedores"
            description="Consultá facturas y notas, su vencimiento y el saldo pendiente con cada proveedor."
          />
          <Button asChild className="shrink-0">
            <Link href={create()}>
              <Plus className="size-4" />
              Nuevo comprobante
            </Link>
          </Button>
        </div>

        <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Proveedor</TableHead>
                <TableHead>Comprobante</TableHead>
                <TableHead>Fechas</TableHead>
                <TableHead className="text-right">Importe</TableHead>
                <TableHead className="text-right">Saldo pendiente</TableHead>
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
                      <span>No hay comprobantes registrados.</span>
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
                            className="w-fit border-error-fg/30 bg-error-bg text-error-fg"
                            variant="outline"
                          >
                            <CalendarClock className="size-3" />
                            Vencido
                          </Badge>
                        )}
                      </div>
                    </TableCell>
                    <TableCell className="text-right font-medium tabular-nums">
                      {formatCurrency(voucher.total_amount)}
                    </TableCell>
                    <TableCell className="text-right font-semibold tabular-nums">
                      {formatCurrency(voucher.pending_balance)}
                    </TableCell>
                    <TableCell>
                      <Badge
                        variant="outline"
                        className={statusClasses[voucher.status]}
                      >
                        {voucher.status_label}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <Button variant="outline" size="sm" asChild>
                        <a href={pdf(voucher.id).url}>
                          <Download className="size-4" />
                          PDF
                        </a>
                      </Button>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>

        <TablePagination
          currentPage={vouchers.current_page}
          totalPages={vouchers.last_page}
          totalItems={vouchers.total}
          pageSize={vouchers.per_page}
          onPageChange={changePage}
          entityName="comprobantes"
        />
      </div>
    </>
  );
}

SupplierVouchersIndex.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Comprobantes de proveedores', href: index() },
  ] satisfies BreadcrumbItem[],
};
