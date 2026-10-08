import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Eye, PackageSearch } from 'lucide-react';
import TablePagination from '@/components/table-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardTitle } from '@/components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { home } from '@/routes/tienda';
import { index, show } from '@/routes/tienda/orders';
import type { BreadcrumbItem } from '@/types';

type WebOrder = App.Data.Ecommerce.WebOrderListData;

type Props = {
  orders: {
    data: WebOrder[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
};

export default function OrdersIndex({ orders }: Props) {
  const goToPage = (page: number) => {
    router.get(index.url({ query: { page } }), {}, { preserveScroll: true });
  };

  return (
    <>
      <Head title="Mis pedidos | Tienda Online" />

      <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
            Mis pedidos
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Consultá el estado y el detalle de los pedidos que confirmaste.
          </p>
        </div>

        {orders.data.length === 0 ? (
          <Card className="my-8 flex flex-col items-center justify-center border-dashed p-10 text-center shadow-xs">
            <div className="mb-4 flex size-20 items-center justify-center rounded-full bg-muted/60 text-muted-foreground">
              <PackageSearch className="size-10" />
            </div>
            <CardTitle className="text-xl font-bold">
              Todavía no hiciste pedidos
            </CardTitle>
            <CardDescription className="mt-2 max-w-sm text-sm">
              Cuando confirmes una compra desde tu carrito, la vas a encontrar
              acá.
            </CardDescription>
            <Button asChild className="mt-6 gap-2">
              <Link href={home.url()}>
                <ArrowLeft className="size-4" />
                Explorar catálogo
              </Link>
            </Button>
          </Card>
        ) : (
          <>
            <div className="rounded-xl border border-border bg-card shadow-xs">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Pedido</TableHead>
                    <TableHead>Fecha</TableHead>
                    <TableHead>Entrega</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead className="text-right">Artículos</TableHead>
                    <TableHead className="text-right">Total</TableHead>
                    <TableHead className="text-right" />
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {orders.data.map((order) => (
                    <TableRow key={order.id}>
                      <TableCell className="font-semibold tabular-nums">
                        N.º {order.formatted_number}
                      </TableCell>
                      <TableCell className="text-muted-foreground">
                        {order.placed_at_formatted}
                      </TableCell>
                      <TableCell>{order.delivery_method_label}</TableCell>
                      <TableCell>
                        <Badge
                          variant={
                            order.status === 'pagado' ? 'default' : 'secondary'
                          }
                        >
                          {order.status_label}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right tabular-nums">
                        {order.items_count}
                      </TableCell>
                      <TableCell className="text-right font-semibold tabular-nums">
                        {order.formatted_total_amount}
                      </TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm" asChild>
                          <Link href={show(order.id)}>
                            <Eye className="mr-1.5 size-4" />
                            Ver
                          </Link>
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>

            <TablePagination
              currentPage={orders.current_page}
              totalPages={orders.last_page}
              totalItems={orders.total}
              pageSize={orders.per_page}
              onPageChange={goToPage}
              entityName="pedidos"
            />
          </>
        )}
      </div>
    </>
  );
}

OrdersIndex.layout = {
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
