import { Head, Link, usePage } from '@inertiajs/react';
import {
  ArrowRight,
  CheckCircle2,
  ShieldCheck,
  ShoppingCart,
  UserCheck,
} from 'lucide-react';
import React from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem } from '@/types';

export default function StoreHome() {
  const { auth, flash } = usePage().props;
  const user = auth?.user;
  const flashSuccess =
    typeof flash?.success === 'string' ? flash.success : null;

  return (
    <>
      <Head title="Tienda Online | Supermercados La Linda" />

      <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
        {flashSuccess && (
          <Alert className="border-green-600/30 bg-green-500/10 text-green-700 dark:border-green-500/30 dark:text-green-400">
            <CheckCircle2 className="size-4 text-green-600 dark:text-green-400" />
            <AlertTitle>Operación exitosa</AlertTitle>
            <AlertDescription>{flashSuccess}</AlertDescription>
          </Alert>
        )}

        {/* Hero Section */}
        <section className="relative overflow-hidden rounded-2xl border border-border bg-gradient-to-br from-primary/15 via-primary/5 to-background p-8 shadow-xs md:p-12">
          <div className="max-w-2xl space-y-4">
            <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/20 px-3 py-1 text-xs font-semibold text-primary">
              <ShieldCheck className="size-3.5" /> Compras online seguras
            </span>
            <h1 className="text-3xl font-extrabold tracking-tight text-foreground sm:text-4xl">
              Bienvenido a la Tienda Online
            </h1>
            <p className="text-base text-muted-foreground sm:text-lg">
              Comprá desde la comodidad de tu casa con los mejores precios,
              promociones vigentes y retiro en sucursal o envío a domicilio.
            </p>
            <div className="flex flex-wrap gap-3 pt-2">
              {user ? (
                <Button asChild size="lg" className="gap-2">
                  <Link href="/tienda/mi-cuenta">
                    <UserCheck className="size-4" />
                    Mi cuenta ({user.name})
                  </Link>
                </Button>
              ) : (
                <>
                  <Button asChild size="lg" className="gap-2">
                    <Link href="/register">
                      Crear mi cuenta
                      <ArrowRight className="size-4" />
                    </Link>
                  </Button>
                  <Button asChild variant="outline" size="lg">
                    <Link href="/login">Iniciar sesión</Link>
                  </Button>
                </>
              )}
            </div>
          </div>
        </section>

        {/* Feature info */}
        <section className="grid gap-6 sm:grid-cols-3">
          <div className="rounded-xl border border-border bg-card p-6 shadow-xs">
            <div className="mb-3 flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
              <ShoppingCart className="size-5" />
            </div>
            <h3 className="font-semibold text-foreground">Catálogo en vivo</h3>
            <p className="mt-1 text-sm text-muted-foreground">
              Precios vigentes y disponibilidad de productos actualizados en
              tiempo real para consumidor final.
            </p>
          </div>

          <div className="rounded-xl border border-border bg-card p-6 shadow-xs">
            <div className="mb-3 flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
              <ShieldCheck className="size-5" />
            </div>
            <h3 className="font-semibold text-foreground">
              Tu cuenta protegida
            </h3>
            <p className="mt-1 text-sm text-muted-foreground">
              Mantené tus datos de contacto al día y consultá el estado de todos
              tus pedidos en un solo lugar.
            </p>
          </div>

          <div className="rounded-xl border border-border bg-card p-6 shadow-xs">
            <div className="mb-3 flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
              <UserCheck className="size-5" />
            </div>
            <h3 className="font-semibold text-foreground">
              Precios preferenciales
            </h3>
            <p className="mt-1 text-sm text-muted-foreground">
              Si contás con una lista de precios preferencial asignada a tu
              cuenta, tus precios se aplican automáticamente.
            </p>
          </div>
        </section>
      </div>
    </>
  );
}

StoreHome.layout = {
  breadcrumbs: [
    {
      title: 'Tienda Online',
      href: '/tienda',
    },
  ] satisfies BreadcrumbItem[],
};
