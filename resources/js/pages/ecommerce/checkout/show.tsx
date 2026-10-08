import { Head, Link, useForm } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, CheckCircle2, MapPin } from 'lucide-react';
import React from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import * as cartRoutes from '@/routes/tienda/cart';
import * as checkoutRoutes from '@/routes/tienda/checkout';
import type { BreadcrumbItem } from '@/types';

type CartData = App.Data.Ecommerce.CartData;
type PickupBranch = App.Data.Ecommerce.PickupBranchOptionData;

type Props = {
  cart: CartData;
  branches: PickupBranch[];
};

export default function CheckoutShow({ cart, branches }: Props) {
  const form = useForm<{
    pickup_branch_id: string;
    notes: string;
    cart?: string;
  }>({
    pickup_branch_id: branches.length === 1 ? String(branches[0].id) : '',
    notes: '',
  });

  const selectedBranch = branches.find(
    (branch) => String(branch.id) === form.data.pickup_branch_id,
  );
  const cartError = form.errors.cart;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    form.post(checkoutRoutes.store.url(), { preserveScroll: true });
  };

  return (
    <>
      <Head title="Confirmar pedido | Tienda Online" />

      <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
            Confirmar pedido
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Revisá tu compra, elegí dónde retirarla y confirmá el pedido.
          </p>
        </div>

        {(cartError || cart.has_unavailable_items) && (
          <Alert
            variant="destructive"
            className="border-destructive/30 bg-destructive/10 text-destructive"
          >
            <AlertCircle className="size-4" />
            <AlertTitle className="font-semibold">
              No se puede confirmar el pedido
            </AlertTitle>
            <AlertDescription className="text-xs">
              {cartError ??
                'Hay artículos no disponibles en tu carrito. Quitalos para continuar.'}
            </AlertDescription>
          </Alert>
        )}

        <form onSubmit={handleSubmit} className="grid gap-6 lg:grid-cols-3">
          <div className="space-y-6 lg:col-span-2">
            {/* Pickup */}
            <Card className="border-border shadow-xs">
              <CardHeader>
                <CardTitle className="text-lg font-semibold">
                  Retiro en sucursal
                </CardTitle>
                <CardDescription>
                  Te avisamos cuando tu pedido esté listo para retirar.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-1.5">
                  <Label htmlFor="pickup_branch_id" className="text-sm">
                    Sucursal de retiro{' '}
                    <span className="text-destructive">*</span>
                  </Label>
                  <Select
                    value={form.data.pickup_branch_id}
                    onValueChange={(value) =>
                      form.setData('pickup_branch_id', value)
                    }
                  >
                    <SelectTrigger
                      id="pickup_branch_id"
                      className="w-full"
                      aria-invalid={!!form.errors.pickup_branch_id}
                    >
                      <SelectValue placeholder="Elegí una sucursal" />
                    </SelectTrigger>
                    <SelectContent>
                      {branches.map((branch) => (
                        <SelectItem key={branch.id} value={String(branch.id)}>
                          {branch.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {selectedBranch?.address && (
                    <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                      <MapPin className="size-3.5" />
                      {selectedBranch.address}
                    </p>
                  )}
                  {branches.length === 0 && (
                    <p className="text-xs text-destructive">
                      No hay sucursales habilitadas para retiro en este momento.
                    </p>
                  )}
                  {form.errors.pickup_branch_id && (
                    <p className="text-xs text-destructive">
                      {form.errors.pickup_branch_id}
                    </p>
                  )}
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="notes" className="text-sm">
                    Observaciones
                  </Label>
                  <Textarea
                    id="notes"
                    value={form.data.notes}
                    onChange={(e) => form.setData('notes', e.target.value)}
                    placeholder="Por ejemplo: retiro después de las 18 hs."
                    maxLength={500}
                    rows={3}
                    aria-invalid={!!form.errors.notes}
                  />
                  {form.errors.notes && (
                    <p className="text-xs text-destructive">
                      {form.errors.notes}
                    </p>
                  )}
                </div>
              </CardContent>
            </Card>

            {/* Items */}
            <Card className="border-border shadow-xs">
              <CardHeader className="pb-3">
                <CardTitle className="text-lg font-semibold">
                  Artículos ({cart.lines_count})
                </CardTitle>
              </CardHeader>
              <CardContent className="divide-y divide-border p-0">
                {cart.items.map((item) => (
                  <div
                    key={item.id}
                    className={`flex items-center justify-between gap-4 px-6 py-3 text-sm ${
                      !item.is_available ? 'bg-destructive/5' : ''
                    }`}
                  >
                    <div className="min-w-0 space-y-0.5">
                      <div className="flex flex-wrap items-center gap-1.5">
                        <span className="font-medium text-foreground">
                          {item.article_description}
                        </span>
                        {!item.is_available && (
                          <Badge
                            variant="destructive"
                            className="text-[10px] font-semibold"
                          >
                            No disponible
                          </Badge>
                        )}
                      </div>
                      <p className="text-xs text-muted-foreground">
                        {item.quantity}{' '}
                        {item.unit_of_measure_abbreviation || 'u.'}
                        {item.is_available
                          ? ` × ${item.formatted_unit_price}`
                          : ` · ${item.unavailable_reason ?? 'No disponible'}`}
                      </p>
                    </div>
                    <span className="shrink-0 font-semibold text-foreground tabular-nums">
                      {item.formatted_subtotal}
                    </span>
                  </div>
                ))}
              </CardContent>
            </Card>

            <Button variant="ghost" asChild className="gap-2 text-sm">
              <Link href={cartRoutes.index.url()}>
                <ArrowLeft className="size-4" />
                Volver al carrito
              </Link>
            </Button>
          </div>

          {/* Summary */}
          <div className="lg:col-span-1">
            <Card className="sticky top-6 border-border shadow-xs">
              <CardHeader className="pb-3">
                <CardTitle className="text-lg font-semibold">
                  Resumen del pedido
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3 text-sm">
                <div className="flex justify-between text-muted-foreground">
                  <span>Subtotal de artículos</span>
                  <span className="font-semibold text-foreground">
                    {cart.formatted_total}
                  </span>
                </div>
                <div className="flex justify-between text-muted-foreground">
                  <span>Retiro en sucursal</span>
                  <span className="font-semibold text-foreground">
                    Sin costo
                  </span>
                </div>
                <Separator />
                <div className="flex items-baseline justify-between pt-1">
                  <span className="text-base font-bold text-foreground">
                    Total
                  </span>
                  <span className="text-2xl font-extrabold tracking-tight text-primary">
                    {cart.formatted_total}
                  </span>
                </div>
                <p className="pt-1 text-[11px] leading-relaxed text-muted-foreground">
                  Los precios se confirman al momento de enviar el pedido y
                  quedan fijos desde entonces. El pedido queda pendiente de
                  pago.
                </p>
              </CardContent>
              <CardFooter className="pt-2">
                <Button
                  type="submit"
                  size="lg"
                  className="w-full gap-2 font-semibold shadow-xs"
                  disabled={
                    form.processing ||
                    cart.has_unavailable_items ||
                    branches.length === 0
                  }
                >
                  <CheckCircle2 className="size-4" />
                  {form.processing ? 'Confirmando...' : 'Confirmar pedido'}
                </Button>
              </CardFooter>
            </Card>
          </div>
        </form>
      </div>
    </>
  );
}

CheckoutShow.layout = {
  breadcrumbs: [
    {
      title: 'Tienda Online',
      href: '/tienda',
    },
    {
      title: 'Carrito de compras',
      href: '/tienda/carrito',
    },
    {
      title: 'Confirmar pedido',
      href: '/tienda/checkout',
    },
  ] satisfies BreadcrumbItem[],
};
