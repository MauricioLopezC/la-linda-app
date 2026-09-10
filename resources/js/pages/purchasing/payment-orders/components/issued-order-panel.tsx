import { CheckCircle2, Plus } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { formatCurrency } from '@/lib/utils';

type PaymentOrder = App.Data.Purchasing.PaymentOrderData;

type Props = {
  order: PaymentOrder;
  onDismiss: () => void;
};

export default function IssuedOrderPanel({ order, onDismiss }: Props) {
  return (
    <Card className="border-emerald-300 bg-emerald-50/60 shadow-xs dark:border-emerald-800 dark:bg-emerald-950/20">
      <CardHeader className="border-b border-emerald-200 pb-3 dark:border-emerald-900">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <CardTitle className="flex items-center gap-2 text-base font-semibold text-emerald-800 dark:text-emerald-300">
            <CheckCircle2 className="size-5" />
            Orden de pago {order.order_number} emitida
          </CardTitle>
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={onDismiss}
            className="gap-1"
          >
            <Plus className="size-4" />
            Emitir otra orden
          </Button>
        </div>
      </CardHeader>

      <CardContent className="space-y-4 pt-4">
        <div className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
          <Field label="Proveedor">{order.supplier_name}</Field>
          <Field label="Medio de pago">{order.payment_method_name}</Field>
          <Field label="Fecha">{order.date}</Field>
          <Field label="Estado">
            <Badge
              variant="outline"
              className="border-emerald-300 bg-emerald-100 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
            >
              {order.status_label}
            </Badge>
          </Field>
        </div>

        <div className="rounded-md border bg-background">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Factura</TableHead>
                <TableHead className="text-right">Importe imputado</TableHead>
                <TableHead className="text-right">Saldo restante</TableHead>
                <TableHead>Estado de la factura</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {order.items.map((item) => (
                <TableRow key={item.supplier_voucher_id}>
                  <TableCell className="font-medium">
                    Factura #{item.supplier_voucher_id}
                  </TableCell>
                  <TableCell className="text-right font-mono">
                    {formatCurrency(item.amount_applied)}
                  </TableCell>
                  <TableCell className="text-right font-mono">
                    {formatCurrency(item.voucher_remaining_balance)}
                  </TableCell>
                  <TableCell>
                    <Badge variant="secondary" className="font-normal">
                      {item.voucher_status_label}
                    </Badge>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>

        <div className="flex items-center justify-end gap-3">
          <span className="text-sm font-semibold text-muted-foreground uppercase">
            Total de la orden
          </span>
          <span className="font-mono text-2xl font-bold text-emerald-700 dark:text-emerald-400">
            {formatCurrency(order.total_amount)}
          </span>
        </div>
      </CardContent>
    </Card>
  );
}

function Field({
  label,
  children,
}: {
  label: string;
  children: React.ReactNode;
}) {
  return (
    <div className="space-y-0.5">
      <div className="text-xs font-medium text-muted-foreground uppercase">
        {label}
      </div>
      <div className="font-medium">{children}</div>
    </div>
  );
}
