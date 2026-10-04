import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, ShoppingBasket, Store } from 'lucide-react';
import React from 'react';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

export default function Welcome() {
  const { auth } = usePage().props;
  const user = auth?.user;
  const isClient = user?.role === 'cliente';

  return (
    <>
      <Head title="Bienvenido" />
      <div className="flex min-h-screen flex-col bg-background text-foreground">
        <header className="w-full px-6 py-6 lg:px-8">
          <nav className="mx-auto flex max-w-4xl items-center justify-between">
            <span className="text-lg font-bold text-primary">La Linda</span>
            <div className="flex items-center gap-3">
              <Button asChild variant="outline" size="sm" className="gap-2">
                <Link href="/tienda">
                  <Store className="size-4 text-primary" />
                  Tienda Online
                </Link>
              </Button>
              {user ? (
                <Button asChild size="sm">
                  <Link href={isClient ? '/tienda' : dashboard()}>
                    {isClient ? 'Mi Tienda' : 'Dashboard'}
                  </Link>
                </Button>
              ) : (
                <Button asChild variant="ghost" size="sm">
                  <Link href={login()}>Iniciar sesión</Link>
                </Button>
              )}
            </div>
          </nav>
        </header>

        <main className="flex flex-1 flex-col items-center justify-center gap-6 px-6 pb-24 text-center">
          <div className="flex size-16 items-center justify-center rounded-full bg-primary-100 text-primary dark:bg-primary-900">
            <ShoppingBasket className="size-8" />
          </div>
          <div className="flex flex-col gap-2">
            <h1 className="text-3xl font-bold tracking-tight text-balance lg:text-4xl">
              Supermercados La Linda
            </h1>
            <p className="max-w-md text-base text-balance text-muted-foreground">
              Gestión de inventario, compras, ventas, e-commerce y catálogo en
              un solo lugar.
            </p>
          </div>
          <div className="flex flex-wrap items-center justify-center gap-3 pt-4">
            <Button asChild size="lg" className="gap-2">
              <Link href="/tienda">
                <Store className="size-4" />
                Ir a la Tienda Online
                <ArrowRight className="size-4" />
              </Link>
            </Button>
            {!user && (
              <Button asChild variant="outline" size="lg">
                <Link href={login()}>Iniciar sesión</Link>
              </Button>
            )}
          </div>
        </main>
      </div>
    </>
  );
}
