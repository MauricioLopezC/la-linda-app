import { Head, Link, router } from '@inertiajs/react';
import {
  AlertCircle,
  ArrowLeft,
  ArrowRight,
  Minus,
  Package,
  Plus,
  ShoppingCart,
  Trash2,
} from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';
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
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { home } from '@/routes/tienda';
import * as cartRoutes from '@/routes/tienda/cart';
import type { BreadcrumbItem } from '@/types';

type CartData = App.Data.Ecommerce.CartData;
type CartItem = App.Data.Ecommerce.CartItemData;

type Props = {
  cart: CartData;
};

function CartItemThumbnail({ src, alt }: { src?: string | null; alt: string }) {
  const [hasError, setHasError] = useState(false);

  if (src && !hasError) {
    return (
      <img
        src={src}
        alt={alt}
        className="size-16 shrink-0 rounded-lg border border-border object-cover sm:size-20"
        loading="lazy"
        onError={() => setHasError(true)}
      />
    );
  }

  return (
    <div className="flex size-16 shrink-0 items-center justify-center rounded-lg border border-border bg-muted/40 text-muted-foreground/60 sm:size-20">
      <Package className="size-8" />
    </div>
  );
}

export default function CartIndex({ cart }: Props) {
  const [updatingItemId, setUpdatingItemId] = useState<number | null>(null);
  const [isClearDialogOpen, setIsClearDialogOpen] = useState(false);
  const [isClearing, setIsClearing] = useState(false);

  const availableItemsCount = cart.items.filter(
    (item) => item.is_available,
  ).length;

  const handleUpdateQuantity = (item: CartItem, delta: number) => {
    const current = parseFloat(item.quantity);
    const step = item.allows_decimals ? 0.5 : 1;
    const min = item.allows_decimals ? 0.5 : 1;
    const newQty = Math.max(
      min,
      Math.round((current + delta * step) * 10) / 10,
    );

    if (newQty === current) {
      return;
    }

    setUpdatingItemId(item.id);
    router.patch(
      cartRoutes.update.url({ cart_item: item.id }),
      { quantity: newQty },
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(
            `Cantidad de "${item.article_description}" actualizada a ${newQty} ${item.unit_of_measure_abbreviation || 'u.'}`,
          );
        },
        onError: (errors) => {
          const firstError = Object.values(errors)[0];
          toast.error(
            typeof firstError === 'string'
              ? firstError
              : 'Error al actualizar la cantidad del artículo.',
          );
        },
        onFinish: () => setUpdatingItemId(null),
      },
    );
  };

  const handleRemoveItem = (item: CartItem) => {
    setUpdatingItemId(item.id);
    router.delete(cartRoutes.destroy.url({ cart_item: item.id }), {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(
          `Eliminaste "${item.article_description}" de tu carrito de compras`,
        );
      },
      onError: () => {
        toast.error('Error al eliminar el artículo del carrito.');
      },
      onFinish: () => setUpdatingItemId(null),
    });
  };

  const handleClearCart = () => {
    setIsClearing(true);
    router.delete(cartRoutes.clear.url(), {
      preserveScroll: true,
      onSuccess: () => {
        setIsClearDialogOpen(false);
        toast.success('El carrito de compras se vació correctamente.');
      },
      onError: () => {
        toast.error('Error al vaciar el carrito.');
      },
      onFinish: () => setIsClearing(false),
    });
  };

  const handleContinueCheckout = () => {
    if (availableItemsCount === 0) {
      toast.error('No tienes artículos disponibles para iniciar una compra.');

      return;
    }

    toast.info(
      'El proceso de confirmación de pedido y checkout estará disponible próximamente en la siguiente etapa.',
    );
  };

  return (
    <>
      <Head title="Carrito de compras | Tienda Online" />

      <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
        {/* Header */}
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
            Carrito de compras
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Revisá tus productos seleccionados y confirma las cantidades antes
            de continuar con tu pedido.
          </p>
        </div>

        {/* Empty state */}
        {cart.items.length === 0 ? (
          <Card className="my-8 flex flex-col items-center justify-center border-dashed p-10 text-center shadow-xs">
            <div className="mb-4 flex size-20 items-center justify-center rounded-full bg-muted/60 text-muted-foreground">
              <ShoppingCart className="size-10" />
            </div>
            <CardTitle className="text-xl font-bold">
              Tu carrito está vacío
            </CardTitle>
            <CardDescription className="mt-2 max-w-sm text-sm">
              Aún no has agregado ningún producto a tu carrito de compras.
              Explorá nuestro catálogo online para encontrar los mejores
              precios.
            </CardDescription>
            <Button asChild size="default" className="mt-6 gap-2">
              <Link href={home.url()}>
                <ArrowLeft className="size-4" />
                Explorar catálogo
              </Link>
            </Button>
          </Card>
        ) : (
          <div className="grid gap-6 lg:grid-cols-3">
            {/* Main Cart Items Column */}
            <div className="space-y-4 lg:col-span-2">
              {/* Unavailable Items Warning */}
              {cart.has_unavailable_items && (
                <Alert
                  variant="destructive"
                  className="border-destructive/30 bg-destructive/10 text-destructive"
                >
                  <AlertCircle className="size-4" />
                  <AlertTitle className="font-semibold">
                    Atención: Hay artículos no disponibles
                  </AlertTitle>
                  <AlertDescription className="text-xs">
                    Algunos artículos en tu carrito ya no están disponibles para
                    la venta online (sin precio vigente o dados de baja). Se
                    muestran señalizados y han sido excluidos del total a pagar.
                  </AlertDescription>
                </Alert>
              )}

              {/* Items Card */}
              <Card className="border-border shadow-xs">
                <CardHeader className="flex flex-row items-center justify-between pb-4">
                  <div>
                    <CardTitle className="text-lg font-semibold">
                      Productos seleccionados ({cart.lines_count})
                    </CardTitle>
                    <CardDescription className="text-xs">
                      {availableItemsCount}{' '}
                      {availableItemsCount === 1 ? 'disponible' : 'disponibles'}
                      {cart.has_unavailable_items &&
                        ` · ${cart.lines_count - availableItemsCount} no disponible(s)`}
                    </CardDescription>
                  </div>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="gap-1.5 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive"
                    onClick={() => setIsClearDialogOpen(true)}
                  >
                    <Trash2 className="size-3.5" />
                    Vaciar carrito
                  </Button>
                </CardHeader>

                <CardContent className="divide-y divide-border p-0">
                  {cart.items.map((item) => {
                    const isBusy = updatingItemId === item.id;
                    const qtyNum = parseFloat(item.quantity);
                    const minQty = item.allows_decimals ? 0.5 : 1;
                    const canDecrease = qtyNum > minQty;

                    return (
                      <div
                        key={item.id}
                        className={`flex flex-col gap-4 p-4 transition-colors sm:flex-row sm:items-center sm:justify-between ${
                          !item.is_available
                            ? 'bg-destructive/5'
                            : 'hover:bg-muted/20'
                        }`}
                      >
                        {/* Item Details */}
                        <div className="flex items-start gap-3 sm:items-center">
                          <CartItemThumbnail
                            src={item.article_image_url}
                            alt={item.article_description}
                          />

                          <div className="min-w-0 flex-1 space-y-1">
                            <div className="flex flex-wrap items-center gap-1.5">
                              <Badge
                                variant="secondary"
                                className="text-[10px] font-normal"
                              >
                                {item.category_name}
                              </Badge>
                              {item.brand_name && (
                                <span className="text-[11px] font-medium text-muted-foreground uppercase">
                                  {item.brand_name}
                                </span>
                              )}
                              {!item.is_available && (
                                <Badge
                                  variant="destructive"
                                  className="text-[10px] font-semibold"
                                >
                                  No disponible
                                </Badge>
                              )}
                            </div>

                            <h3 className="text-sm font-semibold text-foreground sm:text-base">
                              {item.article_description}
                            </h3>

                            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                              <span>Cód: {item.article_internal_code}</span>
                              {item.article_barcode && (
                                <>
                                  <span>·</span>
                                  <span>EAN: {item.article_barcode}</span>
                                </>
                              )}
                            </div>

                            {!item.is_available ? (
                              <p className="text-xs font-medium text-destructive">
                                {item.unavailable_reason ||
                                  'Este producto ya no se encuentra disponible.'}
                              </p>
                            ) : (
                              <p className="text-xs font-medium text-muted-foreground">
                                {item.formatted_unit_price} /{' '}
                                {item.unit_of_measure_abbreviation || 'u.'}
                              </p>
                            )}
                          </div>
                        </div>

                        {/* Controls & Subtotal */}
                        <div className="flex items-center justify-between gap-4 border-t border-border/50 pt-3 sm:border-t-0 sm:pt-0">
                          {/* Quantity Controls */}
                          {item.is_available ? (
                            <div className="flex items-center rounded-lg border border-border bg-muted/30 p-0.5">
                              <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7 rounded-md text-muted-foreground hover:text-foreground"
                                disabled={isBusy || !canDecrease}
                                onClick={() => handleUpdateQuantity(item, -1)}
                                title="Disminuir cantidad"
                              >
                                <Minus className="size-3" />
                              </Button>
                              <span className="w-10 text-center text-xs font-semibold tabular-nums">
                                {item.quantity}
                              </span>
                              <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-7 rounded-md text-muted-foreground hover:text-foreground"
                                disabled={isBusy}
                                onClick={() => handleUpdateQuantity(item, 1)}
                                title="Aumentar cantidad"
                              >
                                <Plus className="size-3" />
                              </Button>
                            </div>
                          ) : (
                            <Badge
                              variant="outline"
                              className="text-xs text-muted-foreground"
                            >
                              Cant: {item.quantity}{' '}
                              {item.unit_of_measure_abbreviation || 'u.'}
                            </Badge>
                          )}

                          {/* Subtotal */}
                          <div className="text-right">
                            {item.is_available ? (
                              <div className="text-base font-bold text-foreground sm:text-lg">
                                {item.formatted_subtotal}
                              </div>
                            ) : (
                              <div className="space-y-0.5">
                                <span className="text-sm font-semibold text-muted-foreground line-through">
                                  $ 0,00
                                </span>
                                <p className="text-[10px] text-muted-foreground">
                                  No suma al total
                                </p>
                              </div>
                            )}
                          </div>

                          {/* Remove button */}
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            disabled={isBusy}
                            className="size-8 rounded-lg text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                            onClick={() => handleRemoveItem(item)}
                            title="Eliminar del carrito"
                          >
                            <Trash2 className="size-4" />
                          </Button>
                        </div>
                      </div>
                    );
                  })}
                </CardContent>
              </Card>

              {/* Continue Shopping Link */}
              <div className="pt-2">
                <Button variant="ghost" asChild className="gap-2 text-sm">
                  <Link href={home.url()}>
                    <ArrowLeft className="size-4" />
                    Continuar comprando en el catálogo
                  </Link>
                </Button>
              </div>
            </div>

            {/* Order Summary Sidebar */}
            <div className="space-y-4 lg:col-span-1">
              <Card className="sticky top-6 border-border shadow-xs">
                <CardHeader className="pb-3">
                  <CardTitle className="text-lg font-semibold">
                    Resumen del pedido
                  </CardTitle>
                  <CardDescription className="text-xs">
                    Cálculo estimado con precios vigentes online
                  </CardDescription>
                </CardHeader>

                <CardContent className="space-y-3 text-sm">
                  <div className="flex justify-between text-muted-foreground">
                    <span>Líneas de productos</span>
                    <span className="font-semibold text-foreground">
                      {cart.lines_count}
                    </span>
                  </div>

                  <div className="flex justify-between text-muted-foreground">
                    <span>Cantidad total de unidades</span>
                    <span className="font-semibold text-foreground">
                      {cart.total_quantity}
                    </span>
                  </div>

                  <div className="flex justify-between text-muted-foreground">
                    <span>Subtotal disponible</span>
                    <span className="font-semibold text-foreground">
                      {cart.formatted_total}
                    </span>
                  </div>

                  <Separator />

                  <div className="flex items-baseline justify-between pt-1">
                    <span className="text-base font-bold text-foreground">
                      Total estimado
                    </span>
                    <span className="text-2xl font-extrabold tracking-tight text-primary">
                      {cart.formatted_total}
                    </span>
                  </div>

                  <p className="pt-1 text-[11px] leading-relaxed text-muted-foreground">
                    * Los precios y la disponibilidad definitiva de stock se
                    validarán en el momento de confirmar la orden de compra.
                  </p>
                </CardContent>

                <CardFooter className="flex flex-col gap-2.5 pt-2">
                  <Button
                    type="button"
                    size="lg"
                    className="w-full gap-2 font-semibold shadow-xs"
                    disabled={availableItemsCount === 0}
                    onClick={handleContinueCheckout}
                  >
                    Continuar compra
                    <ArrowRight className="size-4" />
                  </Button>

                  <Button
                    asChild
                    variant="outline"
                    size="default"
                    className="w-full"
                  >
                    <Link href={home.url()}>Seguir comprando</Link>
                  </Button>
                </CardFooter>
              </Card>
            </div>
          </div>
        )}
      </div>

      {/* Confirmation Dialog: Clear Cart */}
      <Dialog open={isClearDialogOpen} onOpenChange={setIsClearDialogOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>¿Vaciar el carrito de compras?</DialogTitle>
            <DialogDescription>
              Se eliminarán todos los artículos agregados a tu carrito. Esta
              acción no se puede deshacer.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter className="gap-2 sm:gap-0">
            <Button
              type="button"
              variant="outline"
              disabled={isClearing}
              onClick={() => setIsClearDialogOpen(false)}
            >
              Cancelar
            </Button>
            <Button
              type="button"
              variant="destructive"
              disabled={isClearing}
              onClick={handleClearCart}
            >
              {isClearing ? 'Vaciando...' : 'Sí, vaciar carrito'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

CartIndex.layout = {
  breadcrumbs: [
    {
      title: 'Tienda Online',
      href: '/tienda',
    },
    {
      title: 'Carrito de compras',
      href: '/tienda/carrito',
    },
  ] satisfies BreadcrumbItem[],
};
