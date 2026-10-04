import { Head, useForm } from '@inertiajs/react';
import { CreditCard, Mail, Phone, Save, User as UserIcon } from 'lucide-react';
import React from 'react';
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
import type { BreadcrumbItem } from '@/types';

interface Props {
  customer: App.Data.Ecommerce.CustomerProfileData;
}

export default function CustomerAccountShow({ customer }: Props) {
  const { data, setData, put, processing, errors, recentlySuccessful } =
    useForm({
      name: customer.name ?? '',
      phone: customer.phone ?? '',
      address: customer.address ?? '',
    });

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    put('/tienda/mi-cuenta');
  };

  return (
    <>
      <Head title="Mi cuenta | Tienda Online" />

      <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
            Mi cuenta
          </h1>
          <p className="mt-1 text-sm text-muted-foreground">
            Consultá y modificá tus datos personales y de contacto para tus
            pedidos online.
          </p>
        </div>

        <div className="grid gap-6 md:grid-cols-3">
          {/* Account summary card */}
          <Card className="h-fit border-border shadow-xs md:col-span-1">
            <CardHeader className="pb-4 text-center">
              <div className="mx-auto mb-3 flex size-16 items-center justify-center rounded-full bg-primary/10 text-primary">
                <UserIcon className="size-8" />
              </div>
              <CardTitle className="text-lg font-semibold">
                {customer.name}
              </CardTitle>
              <CardDescription className="text-xs break-all">
                {customer.email}
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3 border-t border-border pt-4 text-xs text-muted-foreground">
              <div className="flex items-center gap-2">
                <Mail className="size-3.5 shrink-0 text-muted-foreground" />
                <span className="truncate">{customer.email}</span>
              </div>
              {customer.phone && (
                <div className="flex items-center gap-2">
                  <Phone className="size-3.5 shrink-0 text-muted-foreground" />
                  <span>{customer.phone}</span>
                </div>
              )}
              {customer.id_number && (
                <div className="flex items-center gap-2">
                  <CreditCard className="size-3.5 shrink-0 text-muted-foreground" />
                  <span>DNI: {customer.id_number}</span>
                </div>
              )}
              <div className="border-t border-border pt-2 text-[11px] text-muted-foreground/80">
                Cliente consumidor final
              </div>
            </CardContent>
          </Card>

          {/* Edit contact form */}
          <Card className="border-border shadow-xs md:col-span-2">
            <CardHeader>
              <CardTitle className="text-lg font-semibold">
                Datos de contacto
              </CardTitle>
              <CardDescription>
                Actualizá tus datos para el envío y facturación de tus compras.
              </CardDescription>
            </CardHeader>

            <form onSubmit={handleSubmit}>
              <CardContent className="space-y-4">
                {/* Nombre y apellido */}
                <div className="space-y-1.5">
                  <Label htmlFor="name" className="text-sm font-medium">
                    Nombre y apellido{' '}
                    <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    id="name"
                    type="text"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                    aria-invalid={!!errors.name}
                  />
                  {errors.name && (
                    <p className="text-xs text-destructive">{errors.name}</p>
                  )}
                </div>

                {/* Email (Read only) */}
                <div className="space-y-1.5">
                  <Label
                    htmlFor="account_email"
                    className="text-sm font-medium"
                  >
                    Correo electrónico
                  </Label>
                  <Input
                    id="account_email"
                    type="email"
                    value={customer.email}
                    disabled
                    className="cursor-not-allowed bg-muted/50 opacity-80"
                  />
                  <p className="text-[11px] text-muted-foreground">
                    El correo electrónico identifica tu usuario y no es
                    modificable.
                  </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                  {/* Teléfono */}
                  <div className="space-y-1.5">
                    <Label htmlFor="phone" className="text-sm font-medium">
                      Teléfono
                    </Label>
                    <Input
                      id="phone"
                      type="tel"
                      value={data.phone}
                      onChange={(e) => setData('phone', e.target.value)}
                      placeholder="+54 9 387 1234567"
                      aria-invalid={!!errors.phone}
                    />
                    {errors.phone && (
                      <p className="text-xs text-destructive">{errors.phone}</p>
                    )}
                  </div>

                  {/* DNI (Solo lectura) */}
                  <div className="space-y-1.5">
                    <Label htmlFor="id_number" className="text-sm font-medium">
                      DNI
                    </Label>
                    <Input
                      id="id_number"
                      type="text"
                      value={customer.id_number || 'No especificado'}
                      disabled
                      className="cursor-not-allowed bg-muted/50 opacity-80"
                    />
                    <p className="text-[11px] text-muted-foreground">
                      El DNI no puede ser modificado.
                    </p>
                  </div>
                </div>

                {/* Domicilio */}
                <div className="space-y-1.5">
                  <Label htmlFor="address" className="text-sm font-medium">
                    Domicilio
                  </Label>
                  <Input
                    id="address"
                    type="text"
                    value={data.address}
                    onChange={(e) => setData('address', e.target.value)}
                    placeholder="Av. San Martín 1234"
                    aria-invalid={!!errors.address}
                  />
                  {errors.address && (
                    <p className="text-xs text-destructive">{errors.address}</p>
                  )}
                </div>
              </CardContent>

              <CardFooter className="flex items-center justify-between border-t border-border pt-4">
                {recentlySuccessful ? (
                  <span className="text-xs font-medium text-green-600 dark:text-green-400">
                    Cambios guardados correctamente.
                  </span>
                ) : (
                  <span />
                )}

                <Button
                  type="submit"
                  disabled={processing}
                  className="gap-2 font-medium"
                >
                  <Save className="size-4" />
                  {processing ? 'Guardando...' : 'Guardar cambios'}
                </Button>
              </CardFooter>
            </form>
          </Card>
        </div>
      </div>
    </>
  );
}

CustomerAccountShow.layout = {
  breadcrumbs: [
    {
      title: 'Tienda Online',
      href: '/tienda',
    },
    {
      title: 'Mi cuenta',
      href: '/tienda/mi-cuenta',
    },
  ] satisfies BreadcrumbItem[],
};
