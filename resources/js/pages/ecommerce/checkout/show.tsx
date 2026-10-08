import { Head, Link, useForm } from '@inertiajs/react';
import {
  AlertCircle,
  ArrowLeft,
  CheckCircle2,
  MapPin,
  Store,
  Truck,
} from 'lucide-react';
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
import { Input } from '@/components/ui/input';
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
  default_shipping_address: string | null;
  shipping_cost: string;
  formatted_shipping_cost: string;
};

export default function CheckoutShow({
  cart,
  branches,
  default_shipping_address,
  shipping_cost,
  formatted_shipping_cost,
}: Props) {
  const form = useForm<{
    delivery_method: 'retiro' | 'envio';
    pickup_branch_id: string;
    shipping_address: string;
    shipping_notes: string;
    notes: string;
    cart?: string;
  }>({
    delivery_method: 'retiro',
    pickup_branch_id: branches.length === 1 ? String(branches[0].id) : '',
    shipping_address: default_shipping_address ?? '',
    shipping_notes: '',
    notes: '',
  });

  const selectedBranch = branches.find(
    (branch) => String(branch.id) === form.data.pickup_branch_id,
  );
  const cartError = form.errors.cart;

  const isShipping = form.data.delivery_method === 'envio';
  const shippingCostNumber = Number(shipping_cost) || 0;
  const cartTotalNumber = Number(cart.total) || 0;
  const currentTotalNumber = isShipping
    ? cartTotalNumber + shippingCostNumber
    : cartTotalNumber;

  const formattedCurrentTotal = `$ ${currentTotalNumber.toLocaleString(
    'es-AR',
    {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    },
  )}`;

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
            Revisá tu compra, elegí cómo recibirla y confirmá el pedido.
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
            {/* Modalidad de entrega */}
            <Card className="border-border shadow-xs">
              <CardHeader>
                <CardTitle className="text-lg font-semibold">
                  Modalidad de entrega
                </CardTitle>
                <CardDescription>
                  Elegí si preferís retirar en sucursal o recibir el pedido en
                  tu domicilio.
                </CardDescription>
              </CardHeader>
              <CardContent className="space-y-5">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <button
                    type="button"
                    onClick={() => form.setData('delivery_method', 'retiro')}
                    className={`flex cursor-pointer items-start gap-3 rounded-lg border p-4 text-left transition-colors ${
                      form.data.delivery_method === 'retiro'
                        ? 'border-primary bg-primary/5 ring-1 ring-primary'
                        : 'border-border hover:border-muted-foreground/30'
                    }`}
                  >
                    <Store className="mt-0.5 size-5 shrink-0 text-primary" />
                    <div className="flex-1 space-y-1">
                      <div className="flex items-center justify-between">
                        <span className="font-semibold text-foreground">
                          Retiro en sucursal
                        </span>
                        <Badge variant="secondary" className="text-xs">
                          Sin costo
                        </Badge>
                      </div>
                      <p className="text-xs text-muted-foreground">
                        Retirás en una de nuestras sucursales habilitadas.
                      </p>
                    </div>
                  </button>

                  <button
                    type="button"
                    onClick={() => form.setData('delivery_method', 'envio')}
                    className={`flex cursor-pointer items-start gap-3 rounded-lg border p-4 text-left transition-colors ${
                      form.data.delivery_method === 'envio'
                        ? 'border-primary bg-primary/5 ring-1 ring-primary'
                        : 'border-border hover:border-muted-foreground/30'
                    }`}
                  >
                    <Truck className="mt-0.5 size-5 shrink-0 text-primary" />
                    <div className="flex-1 space-y-1">
                      <div className="flex items-center justify-between">
                        <span className="font-semibold text-foreground">
                          Envío a domicilio
                        </span>
                        <Badge
                          variant="secondary"
                          className="text-xs font-semibold"
                        >
                          {formatted_shipping_cost}
                        </Badge>
                      </div>
                      <p className="text-xs text-muted-foreground">
                        Lo enviamos a tu dirección particular.
                      </p>
                    </div>
                  </button>
                </div>

                {form.errors.delivery_method && (
                  <p className="text-xs text-destructive">
                    {form.errors.delivery_method}
                  </p>
                )}

                {/* Campos según modalidad */}
                {form.data.delivery_method === 'retiro' ? (
                  <div className="space-y-1.5 pt-2">
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
                        No hay sucursales habilitadas para retiro en este
                        momento.
                      </p>
                    )}
                    {form.errors.pickup_branch_id && (
                      <p className="text-xs text-destructive">
                        {form.errors.pickup_branch_id}
                      </p>
                    )}
                  </div>
                ) : (
                  <div className="space-y-4 pt-2">
                    <div className="space-y-1.5">
                      <Label htmlFor="shipping_address" className="text-sm">
                        Domicilio de entrega{' '}
                        <span className="text-destructive">*</span>
                      </Label>
                      <Input
                        id="shipping_address"
                        value={form.data.shipping_address}
                        onChange={(e) =>
                          form.setData('shipping_address', e.target.value)
                        }
                        placeholder="Calle, número, piso/depto, localidad..."
                        maxLength={255}
                        aria-invalid={!!form.errors.shipping_address}
                      />
                      <p className="text-[11px] text-muted-foreground">
                        Podés editar el domicilio para este pedido sin modificar
                        el registrado en tu cuenta.
                      </p>
                      {form.errors.shipping_address && (
                        <p className="text-xs text-destructive">
                          {form.errors.shipping_address}
                        </p>
                      )}
                    </div>

                    <div className="space-y-1.5">
                      <Label htmlFor="shipping_notes" className="text-sm">
                        Indicaciones para la entrega (opcional)
                      </Label>
                      <Input
                        id="shipping_notes"
                        value={form.data.shipping_notes}
                        onChange={(e) =>
                          form.setData('shipping_notes', e.target.value)
                        }
                        placeholder="Por ejemplo: timbre blanco, dejar en portería, entre calles..."
                        maxLength={255}
                        aria-invalid={!!form.errors.shipping_notes}
                      />
                      {form.errors.shipping_notes && (
                        <p className="text-xs text-destructive">
                          {form.errors.shipping_notes}
                        </p>
                      )}
                    </div>
                  </div>
                )}

                <div className="space-y-1.5 pt-2">
                  <Label htmlFor="notes" className="text-sm">
                    Observaciones adicionales
                  </Label>
                  <Textarea
                    id="notes"
                    value={form.data.notes}
                    onChange={(e) => form.setData('notes', e.target.value)}
                    placeholder="Comentarios o indicaciones generales para el pedido..."
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
                  <span>
                    {isShipping ? 'Envío a domicilio' : 'Retiro en sucursal'}
                  </span>
                  <span className="font-semibold text-foreground">
                    {isShipping ? formatted_shipping_cost : 'Sin costo'}
                  </span>
                </div>
                <Separator />
                <div className="flex items-baseline justify-between pt-1">
                  <span className="text-base font-bold text-foreground">
                    Total
                  </span>
                  <span className="text-2xl font-extrabold tracking-tight text-primary">
                    {formattedCurrentTotal}
                  </span>
                </div>
                <p className="pt-1 text-[11px] leading-relaxed text-muted-foreground">
                  Los precios y el costo de envío se confirman al momento de
                  enviar el pedido y quedan fijos desde entonces. El pedido
                  queda pendiente de pago.
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
                    (form.data.delivery_method === 'retiro' &&
                      branches.length === 0)
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
