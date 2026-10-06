import { Head } from '@inertiajs/react';
import type { BreadcrumbItem } from '@/types';

type Props = {
  cart: App.Data.Ecommerce.CartData;
};

export default function CartIndex({ cart }: Props) {
  return (
    <>
      <Head title="Carrito de compras | Tienda Online" />
      <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <h1 className="text-2xl font-bold tracking-tight text-foreground">
          Carrito de compras
        </h1>
        <p className="text-sm text-muted-foreground">
          {cart.lines_count} artículos en tu carrito.
        </p>
      </div>
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
