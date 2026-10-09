import { Head, Link } from '@inertiajs/react';
import {
  ArrowLeft,
  CalendarClock,
  MapPin,
  StickyNote,
  Truck,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { index } from '@/routes/tienda/orders';
import type { BreadcrumbItem } from '@/types';

type Props = {
  order: App.Data.Ecommerce.WebOrderData;
};

export default function OrderShow({ order }: Props) {
  return (
    <>
      <Head title={`Pedido N.º ${order.formatted_number} | Tienda Online`} />

      <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
              Pedido N.º {order.formatted_number}
            </h1>
            <p className="mt-1 flex items-center gap-1.5 text-sm text-muted-foreground">
              <CalendarClock className="size-4" />
              Confirmado el {order.placed_at_formatted}
            </p>
          </div>
          <Badge
            variant={order.status === 'pagado' ? 'default' : 'secondary'}
            className="w-fit text-sm"
          >
            {order.status_label}
          </Badge>
        </div>

        <div className="grid gap-6 lg:grid-cols-3">
          {/* Items */}
          <Card className="border-border shadow-xs lg:col-span-2">
            <CardHeader className="pb-3">
              <CardTitle className="text-lg font-semibold">
                Artículos ({order.items.length})
              </CardTitle>
              <CardDescription className="text-xs">
                Precios fijados al confirmar el pedido.
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="pl-6">Artículo</TableHead>
                    <TableHead className="text-right">Cantidad</TableHead>
                    <TableHead className="text-right">Precio unit.</TableHead>
                    <TableHead className="pr-6 text-right">Subtotal</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {order.items.map((item) => (
                    <TableRow key={item.id}>
                      <TableCell className="pl-6">
                        <div className="font-medium text-foreground">
                          {item.article_description}
                        </div>
                        <div className="text-xs text-muted-foreground">
                          Cód: {item.article_internal_code} · Lista:{' '}
                          {item.price_list_name}
                        </div>
                      </TableCell>
                      <TableCell className="text-right tabular-nums">
                        {item.quantity}{' '}
                        {item.unit_of_measure_abbreviation || 'u.'}
                      </TableCell>
                      <TableCell className="text-right tabular-nums">
                        {item.formatted_unit_price}
                      </TableCell>
                      <TableCell className="pr-6 text-right font-semibold tabular-nums">
                        {item.formatted_line_total}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>

          {/* Summary */}
          <div className="space-y-6 lg:col-span-1">
            <Card className="border-border shadow-xs">
              <CardHeader className="pb-3">
                <CardTitle className="text-lg font-semibold">
                  {order.delivery_method_label}
                </CardTitle>
              </CardHeader>
              <CardContent className="space-y-3 text-sm">
                {order.delivery_method === 'retiro' &&
                  order.pickup_branch_name && (
                    <div className="flex items-start gap-2">
                      <MapPin className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                      <div>
                        <div className="font-medium text-foreground">
                          {order.pickup_branch_name}
                        </div>
                        {order.pickup_branch_address && (
                          <div className="text-xs text-muted-foreground">
                            {order.pickup_branch_address}
                          </div>
                        )}
                      </div>
                    </div>
                  )}
                {order.delivery_method === 'envio' && (
                  <div className="space-y-2">
                    <div className="flex items-start gap-2">
                      <Truck className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                      <div>
                        <div className="text-xs font-medium text-muted-foreground">
                          Domicilio de entrega
                        </div>
                        <div className="font-medium text-foreground">
                          {order.shipping_address || 'No especificado'}
                        </div>
                      </div>
                    </div>
                    {order.shipping_notes && (
                      <div className="flex items-start gap-2 pl-6">
                        <div>
                          <div className="text-xs font-medium text-muted-foreground">
                            Indicaciones
                          </div>
                          <div className="text-xs text-foreground">
                            {order.shipping_notes}
                          </div>
                        </div>
                      </div>
                    )}
                  </div>
                )}
                {order.notes && (
                  <div className="flex items-start gap-2 border-t border-border pt-2">
                    <StickyNote className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                    <div>
                      <div className="text-xs font-medium text-muted-foreground">
                        Observaciones
                      </div>
                      <p className="text-muted-foreground">{order.notes}</p>
                    </div>
                  </div>
                )}
              </CardContent>
            </Card>

            <Card className="border-border shadow-xs">
              <CardHeader className="pb-3">
                <CardTitle className="text-lg font-semibold">Total</CardTitle>
              </CardHeader>
              <CardContent className="space-y-3 text-sm">
                <div className="flex justify-between text-muted-foreground">
                  <span>Subtotal de artículos</span>
                  <span className="font-semibold text-foreground">
                    {order.formatted_items_amount}
                  </span>
                </div>
                <div className="flex justify-between text-muted-foreground">
                  <span>Envío</span>
                  <span className="font-semibold text-foreground">
                    {order.formatted_shipping_cost}
                  </span>
                </div>
                <Separator />
                <div className="flex items-baseline justify-between pt-1">
                  <span className="text-base font-bold text-foreground">
                    Total
                  </span>
                  <span className="text-2xl font-extrabold tracking-tight text-primary">
                    {order.formatted_total_amount}
                  </span>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>

        <Button variant="ghost" asChild className="w-fit gap-2 text-sm">
          <Link href={index.url()}>
            <ArrowLeft className="size-4" />
            Volver a mis pedidos
          </Link>
        </Button>
      </div>
    </>
  );
}

OrderShow.layout = {
  breadcrumbs: [
    {
      title: 'Tienda Online',
      href: '/tienda',
    },
    {
      title: 'Mis pedidos',
      href: '/tienda/mis-pedidos',
    },
  ] satisfies BreadcrumbItem[],
};
