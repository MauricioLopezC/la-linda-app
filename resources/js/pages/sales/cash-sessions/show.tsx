import { Head, Link, useForm } from '@inertiajs/react';
import {
  ArrowDownLeft,
  ArrowUpRight,
  Banknote,
  Clock,
  Lock,
  ShoppingBag,
  Store,
  User,
  Vault,
} from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency } from '@/lib/utils';
import { store as storeMovement } from '@/routes/sales/cash-sessions/movements';
import { index as salesIndex, show as showSale } from '@/routes/sales/sales';

type Session = App.Data.Sales.CashSessionData;
type Movement = App.Data.Sales.CashMovementData;

type Props = {
  cashSession: Session;
};

export default function CashSessionShow({ cashSession }: Props) {
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const availableCash = Number(cashSession.totals.expected_cash);

  const form = useForm({
    type: 'ingreso' as 'ingreso' | 'egreso',
    amount: '',
    reason: '',
  });

  const handleOpenDialog = (type: 'ingreso' | 'egreso') => {
    form.reset();
    form.clearErrors();
    form.setData({
      type,
      amount: '',
      reason: '',
    });
    setIsDialogOpen(true);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    form.post(storeMovement.url(cashSession.id), {
      preserveScroll: true,
      onSuccess: () => {
        setIsDialogOpen(false);
        form.reset();
      },
    });
  };

  const isExpense = form.data.type === 'egreso';
  const enteredAmount = Number(form.data.amount) || 0;
  const exceedsCash = isExpense && enteredAmount > availableCash;

  return (
    <>
      <Head
        title={`Turno #${cashSession.id} · Caja ${cashSession.point_of_sale_number}`}
      />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        {/* Header */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="space-y-1">
            <div className="flex items-center gap-2">
              <Heading
                title={`Turno de Caja #${cashSession.id}`}
                description={`Caja ${cashSession.point_of_sale_number} · ${cashSession.branch_name}`}
              />
              <Badge
                variant={cashSession.is_open ? 'default' : 'secondary'}
                className={
                  cashSession.is_open
                    ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
                    : 'border-zinc-300 bg-zinc-100 text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300'
                }
              >
                {cashSession.is_open ? 'Abierta' : 'Cerrada'}
              </Badge>
            </div>
            <div className="flex flex-wrap items-center gap-4 text-xs text-muted-foreground">
              <span className="flex items-center gap-1">
                <User className="size-3.5" />
                Cajero:{' '}
                <strong className="font-medium text-foreground">
                  {cashSession.user_name}
                </strong>
              </span>
              <span className="flex items-center gap-1">
                <Clock className="size-3.5" />
                Apertura: {cashSession.opened_at_formatted}
              </span>
              {cashSession.closed_at_formatted && (
                <span className="flex items-center gap-1">
                  <Lock className="size-3.5" />
                  Cierre: {cashSession.closed_at_formatted}
                </span>
              )}
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <Button variant="outline" asChild size="sm">
              <Link href={salesIndex.url()}>
                <ShoppingBag className="mr-1.5 size-4" />
                Ver ventas
              </Link>
            </Button>
            {cashSession.is_open && (
              <>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => handleOpenDialog('ingreso')}
                  className="border-emerald-200 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-400 dark:hover:bg-emerald-950/30"
                >
                  <ArrowDownLeft className="mr-1.5 size-4" />
                  Ingreso de efectivo
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => handleOpenDialog('egreso')}
                  className="border-rose-200 text-rose-700 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-400 dark:hover:bg-rose-950/30"
                >
                  <ArrowUpRight className="mr-1.5 size-4" />
                  Egreso de efectivo
                </Button>
              </>
            )}
          </div>
        </div>

        {/* Resumen financiero (Cards / KPIs) */}
        <div className="grid grid-cols-2 gap-4 lg:grid-cols-5">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-xs font-medium text-muted-foreground">
                Fondo inicial
              </CardTitle>
              <Vault className="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-xl font-bold tracking-tight">
                {formatCurrency(cashSession.totals.opening_amount)}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-xs font-medium text-muted-foreground">
                Ventas (Efectivo)
              </CardTitle>
              <Store className="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-xl font-bold tracking-tight">
                {formatCurrency(cashSession.totals.sales_cash_amount)}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-xs font-medium text-muted-foreground">
                Ingresos
              </CardTitle>
              <ArrowDownLeft className="size-4 text-emerald-600 dark:text-emerald-400" />
            </CardHeader>
            <CardContent>
              <div className="text-xl font-bold tracking-tight text-emerald-700 dark:text-emerald-400">
                +{formatCurrency(cashSession.totals.income_amount)}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-xs font-medium text-muted-foreground">
                Egresos
              </CardTitle>
              <ArrowUpRight className="size-4 text-rose-600 dark:text-rose-400" />
            </CardHeader>
            <CardContent>
              <div className="text-xl font-bold tracking-tight text-rose-700 dark:text-rose-400">
                -{formatCurrency(cashSession.totals.expense_amount)}
              </div>
            </CardContent>
          </Card>

          <Card className="col-span-2 border-primary/20 bg-primary/5 lg:col-span-1 dark:border-primary/30">
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-xs font-semibold text-primary">
                Efectivo esperado
              </CardTitle>
              <Banknote className="size-4 text-primary" />
            </CardHeader>
            <CardContent>
              <div className="text-xl font-extrabold tracking-tight text-primary">
                {formatCurrency(cashSession.totals.expected_cash)}
              </div>
              <p className="mt-1 text-[11px] text-muted-foreground">
                Saldo actual en cajón
              </p>
            </CardContent>
          </Card>
        </div>

        {/* Tabla de movimientos del turno */}
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <h3 className="text-base font-semibold">Movimientos del turno</h3>
            <span className="text-xs text-muted-foreground">
              {cashSession.movements.length}{' '}
              {cashSession.movements.length === 1
                ? 'movimiento registrado'
                : 'movimientos registrados'}
            </span>
          </div>

          <div className="overflow-x-auto rounded-xl border border-sidebar-border bg-card shadow-sm">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Fecha y hora</TableHead>
                  <TableHead>Tipo</TableHead>
                  <TableHead>Medio de pago</TableHead>
                  <TableHead>Motivo / Concepto</TableHead>
                  <TableHead>Registrado por</TableHead>
                  <TableHead className="text-right">Importe</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {cashSession.movements.length === 0 ? (
                  <TableRow>
                    <TableCell
                      colSpan={6}
                      className="py-10 text-center text-muted-foreground"
                    >
                      No hay movimientos registrados en este turno.
                    </TableCell>
                  </TableRow>
                ) : (
                  cashSession.movements.map((movement: Movement) => {
                    const isExpenseType = movement.type === 'egreso';
                    const isIncomeType = movement.type === 'ingreso';
                    const isOpeningType = movement.type === 'apertura';
                    const isSaleType = movement.type === 'venta';

                    return (
                      <TableRow key={movement.id}>
                        <TableCell className="font-mono text-xs">
                          {movement.created_at_formatted}
                        </TableCell>
                        <TableCell>
                          {isOpeningType && (
                            <Badge
                              variant="outline"
                              className="border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300"
                            >
                              Apertura
                            </Badge>
                          )}
                          {isSaleType && (
                            <Badge
                              variant="outline"
                              className="border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
                            >
                              Venta
                            </Badge>
                          )}
                          {isIncomeType && (
                            <Badge
                              variant="outline"
                              className="gap-1 border-teal-300 bg-teal-50 text-teal-800 dark:border-teal-800 dark:bg-teal-950/40 dark:text-teal-300"
                            >
                              <ArrowDownLeft className="size-3" />
                              Ingreso
                            </Badge>
                          )}
                          {isExpenseType && (
                            <Badge
                              variant="outline"
                              className="gap-1 border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300"
                            >
                              <ArrowUpRight className="size-3" />
                              Egreso
                            </Badge>
                          )}
                        </TableCell>
                        <TableCell className="text-xs text-muted-foreground">
                          {movement.payment_method_name}
                        </TableCell>
                        <TableCell>
                          {isOpeningType && (
                            <span className="text-muted-foreground italic">
                              Fondo inicial de apertura
                            </span>
                          )}
                          {isSaleType && (
                            <span className="flex items-center gap-1.5">
                              {movement.sale_id ? (
                                <Link
                                  href={showSale.url(movement.sale_id)}
                                  className="font-medium text-primary underline underline-offset-2 hover:opacity-80"
                                >
                                  Cobro de Venta #{movement.sale_id}
                                </Link>
                              ) : (
                                <span>Cobro de venta</span>
                              )}
                            </span>
                          )}
                          {(isIncomeType || isExpenseType) && (
                            <span className="font-medium">
                              {movement.reason}
                            </span>
                          )}
                        </TableCell>
                        <TableCell className="text-xs text-muted-foreground">
                          {movement.user_name}
                        </TableCell>
                        <TableCell className="text-right font-mono font-medium">
                          {isExpenseType ? (
                            <span className="text-rose-600 dark:text-rose-400">
                              - {formatCurrency(movement.amount)}
                            </span>
                          ) : (
                            <span className="text-emerald-600 dark:text-emerald-400">
                              + {formatCurrency(movement.amount)}
                            </span>
                          )}
                        </TableCell>
                      </TableRow>
                    );
                  })
                )}
              </TableBody>
            </Table>
          </div>
        </div>
      </div>

      {/* Modal para registrar movimiento de caja */}
      <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
        <DialogContent className="sm:max-w-md">
          <form onSubmit={handleSubmit} className="space-y-4">
            <DialogHeader>
              <DialogTitle>
                {form.data.type === 'ingreso'
                  ? 'Registrar ingreso de efectivo'
                  : 'Registrar egreso de efectivo'}
              </DialogTitle>
              <DialogDescription>
                {form.data.type === 'ingreso'
                  ? 'Registrá la entrada de dinero en efectivo (ej. refuerzo de cambio de tesorería).'
                  : 'Registrá la salida de dinero en efectivo de la caja (ej. gasto menor, retiro a tesorería).'}
              </DialogDescription>
            </DialogHeader>

            {/* Toggle Tipo */}
            <div className="space-y-1.5">
              <Label>Tipo de movimiento</Label>
              <div className="grid grid-cols-2 gap-2">
                <Button
                  type="button"
                  variant={form.data.type === 'ingreso' ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => form.setData('type', 'ingreso')}
                  className={
                    form.data.type === 'ingreso'
                      ? 'bg-emerald-600 hover:bg-emerald-700'
                      : ''
                  }
                >
                  <ArrowDownLeft className="mr-1.5 size-4" />
                  Ingreso (+)
                </Button>
                <Button
                  type="button"
                  variant={form.data.type === 'egreso' ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => form.setData('type', 'egreso')}
                  className={
                    form.data.type === 'egreso'
                      ? 'bg-rose-600 hover:bg-rose-700'
                      : ''
                  }
                >
                  <ArrowUpRight className="mr-1.5 size-4" />
                  Egreso (-)
                </Button>
              </div>
              <InputError message={form.errors.type} />
            </div>

            {/* Importe */}
            <div className="space-y-1.5">
              <div className="flex items-center justify-between">
                <Label htmlFor="amount">Importe en efectivo ($)</Label>
                {isExpense && (
                  <span className="text-xs text-muted-foreground">
                    Disponible:{' '}
                    <strong className="font-semibold text-foreground">
                      {formatCurrency(availableCash)}
                    </strong>
                  </span>
                )}
              </div>
              <Input
                id="amount"
                type="number"
                step="0.01"
                min="0.01"
                placeholder="0.00"
                value={form.data.amount}
                onChange={(e) => form.setData('amount', e.target.value)}
                autoFocus
                required
              />
              {exceedsCash && (
                <p className="text-xs font-medium text-rose-600 dark:text-rose-400">
                  El importe ingresado supera el efectivo disponible (
                  {formatCurrency(availableCash)}).
                </p>
              )}
              <InputError message={form.errors.amount} />
            </div>

            {/* Motivo */}
            <div className="space-y-1.5">
              <Label htmlFor="reason">Motivo (obligatorio)</Label>
              <Textarea
                id="reason"
                rows={3}
                placeholder={
                  form.data.type === 'ingreso'
                    ? 'Ej. Refuerzo de cambio de billetes chicos, aporte extraordinario...'
                    : 'Ej. Compra de artículos de limpieza, retiro parcial de seguridad, adelanto...'
                }
                value={form.data.reason}
                onChange={(e) => form.setData('reason', e.target.value)}
                required
              />
              <InputError message={form.errors.reason} />
            </div>

            <DialogFooter className="gap-2 sm:gap-0">
              <Button
                type="button"
                variant="ghost"
                onClick={() => setIsDialogOpen(false)}
              >
                Cancelar
              </Button>
              <Button
                type="submit"
                disabled={
                  form.processing ||
                  exceedsCash ||
                  !form.data.amount ||
                  !form.data.reason.trim()
                }
                className={
                  form.data.type === 'ingreso'
                    ? 'bg-emerald-600 hover:bg-emerald-700'
                    : 'bg-rose-600 hover:bg-rose-700'
                }
              >
                {form.processing
                  ? 'Registrando...'
                  : form.data.type === 'ingreso'
                    ? 'Registrar ingreso'
                    : 'Registrar egreso'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}
