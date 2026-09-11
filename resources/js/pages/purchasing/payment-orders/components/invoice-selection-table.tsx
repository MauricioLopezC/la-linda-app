import { Loader2, ReceiptText } from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { formatCurrency } from '@/lib/utils';

type SupplierVoucher = App.Data.Purchasing.SupplierVoucherListData;

export type InvoiceRow = {
  voucher: SupplierVoucher;
  selected: boolean;
  amount: string;
  error: string | null;
};

type Props = {
  rows: InvoiceRow[];
  hasSupplier: boolean;
  loading: boolean;
  onToggle: (voucherId: number, checked: boolean) => void;
  onAmountChange: (voucherId: number, value: string) => void;
  sanitizeAmount: (value: string) => string;
};

export default function InvoiceSelectionTable({
  rows,
  hasSupplier,
  loading,
  onToggle,
  onAmountChange,
  sanitizeAmount,
}: Props) {
  return (
    <div className="rounded-md border">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead className="w-12" />
            <TableHead>Comprobante</TableHead>
            <TableHead>Emisión</TableHead>
            <TableHead>Vencimiento</TableHead>
            <TableHead className="text-right">Saldo pendiente</TableHead>
            <TableHead className="w-48 text-right">Importe a imputar</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {!hasSupplier && (
            <TableRow>
              <TableCell
                colSpan={6}
                className="py-8 text-center text-muted-foreground"
              >
                <ReceiptText className="mx-auto mb-1 size-6 opacity-40" />
                Elegí un proveedor para ver sus facturas con saldo pendiente.
              </TableCell>
            </TableRow>
          )}

          {hasSupplier && loading && (
            <TableRow>
              <TableCell
                colSpan={6}
                className="py-8 text-center text-muted-foreground"
              >
                <Loader2 className="mx-auto mb-1 size-5 animate-spin" />
                Cargando facturas del proveedor...
              </TableCell>
            </TableRow>
          )}

          {hasSupplier && !loading && rows.length === 0 && (
            <TableRow>
              <TableCell
                colSpan={6}
                className="py-8 text-center text-muted-foreground"
              >
                Este proveedor no tiene facturas con saldo pendiente.
              </TableCell>
            </TableRow>
          )}

          {hasSupplier &&
            !loading &&
            rows.map(({ voucher, selected, amount, error }) => (
              <TableRow
                key={voucher.id}
                data-state={selected ? 'selected' : undefined}
              >
                <TableCell>
                  <Checkbox
                    checked={selected}
                    aria-label={`Seleccionar comprobante ${voucher.formatted_number}`}
                    onCheckedChange={(checked) =>
                      onToggle(voucher.id, checked === true)
                    }
                  />
                </TableCell>
                <TableCell>
                  <span className="font-medium">
                    {voucher.formatted_number}
                  </span>
                  <span className="ml-2 text-xs text-muted-foreground">
                    {voucher.type_label}
                  </span>
                </TableCell>
                <TableCell className="text-muted-foreground">
                  {voucher.issue_date_formatted}
                </TableCell>
                <TableCell className="text-muted-foreground">
                  <span className="flex items-center gap-2">
                    {voucher.due_date_formatted ?? '—'}
                    {voucher.is_overdue && (
                      <Badge
                        variant="outline"
                        className="border-rose-300 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300"
                      >
                        Vencida
                      </Badge>
                    )}
                  </span>
                </TableCell>
                <TableCell className="text-right font-mono">
                  {formatCurrency(voucher.outstanding_amount)}
                </TableCell>
                <TableCell className="text-right">
                  <Input
                    type="text"
                    inputMode="decimal"
                    placeholder="0.00"
                    aria-label={`Importe a imputar a ${voucher.formatted_number}`}
                    aria-invalid={error !== null}
                    disabled={!selected}
                    value={selected ? amount : ''}
                    onChange={(event) =>
                      onAmountChange(
                        voucher.id,
                        sanitizeAmount(event.target.value),
                      )
                    }
                    className="h-8 text-right font-mono"
                  />
                  {selected && error && (
                    <InputError message={error} className="mt-1 text-right" />
                  )}
                </TableCell>
              </TableRow>
            ))}
        </TableBody>
      </Table>
    </div>
  );
}
