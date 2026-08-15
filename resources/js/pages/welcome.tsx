import { Head, Link, usePage } from '@inertiajs/react';
import { ShoppingBasket } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';
import { register } from '@/routes';

export default function Welcome() {
  const { auth } = usePage().props;

  return (
    <>
      <Head title="Bienvenido" />
      <div className="flex min-h-screen flex-col bg-background text-foreground">
        <header className="w-full px-6 py-6 lg:px-8">
          <nav className="mx-auto flex max-w-4xl items-center justify-between">
            <span className="text-lg font-bold text-primary">La Linda</span>
            <div className="flex items-center gap-2">
              {auth.user ? (
                <Button asChild>
                  <Link href={dashboard()}>Dashboard</Link>
                </Button>
              ) : (
                <>
                  <Button asChild variant="ghost">
                    <Link href={login()}>Iniciar sesión</Link>
                  </Button>
                  <Button asChild>
                    <Link href={register()}>Registrarse</Link>
                  </Button>
                </>
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
              Sistema de gestión La Linda
            </h1>
            <p className="max-w-md text-base text-balance text-muted-foreground">
              Inventario, punto de venta, e-commerce y empleados en un solo
              lugar.
            </p>
          </div>
        </main>
      </div>
    </>
  );
}
