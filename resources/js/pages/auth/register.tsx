import { Head, useForm } from '@inertiajs/react';
import React from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export default function Register() {
  const { data, setData, post, processing, errors, reset } = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    phone: '',
    address: '',
    id_number: '',
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    post('/register', {
      onFinish: () => reset('password', 'password_confirmation'),
    });
  };

  return (
    <>
      <Head title="Crear cuenta en la tienda online" />

      <form onSubmit={submit} className="flex flex-col gap-6">
        <div className="grid gap-4">
          <div className="grid gap-2">
            <Label htmlFor="name">
              Nombre y apellido <span className="text-destructive">*</span>
            </Label>
            <Input
              id="name"
              type="text"
              name="name"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              required
              autoFocus
              autoComplete="name"
              placeholder="Juan Pérez"
            />
            <InputError message={errors.name} />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="email">
              Correo electrónico <span className="text-destructive">*</span>
            </Label>
            <Input
              id="email"
              type="email"
              name="email"
              value={data.email}
              onChange={(e) => setData('email', e.target.value)}
              required
              autoComplete="email"
              placeholder="correo@ejemplo.com"
            />
            <InputError message={errors.email} />
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
              <Label htmlFor="phone">Teléfono (opcional)</Label>
              <Input
                id="phone"
                type="tel"
                name="phone"
                value={data.phone}
                onChange={(e) => setData('phone', e.target.value)}
                autoComplete="tel"
                placeholder="11 1234-5678"
              />
              <InputError message={errors.phone} />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="id_number">DNI (opcional)</Label>
              <Input
                id="id_number"
                type="text"
                inputMode="numeric"
                name="id_number"
                value={data.id_number}
                onChange={(e) => setData('id_number', e.target.value)}
                placeholder="35123456"
              />
              <InputError message={errors.id_number} />
            </div>
          </div>

          <div className="grid gap-2">
            <Label htmlFor="address">Domicilio de entrega (opcional)</Label>
            <Input
              id="address"
              type="text"
              name="address"
              value={data.address}
              onChange={(e) => setData('address', e.target.value)}
              autoComplete="street-address"
              placeholder="Av. Belgrano 123"
            />
            <InputError message={errors.address} />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="password">
              Contraseña <span className="text-destructive">*</span>
            </Label>
            <PasswordInput
              id="password"
              name="password"
              value={data.password}
              onChange={(e) => setData('password', e.target.value)}
              required
              autoComplete="new-password"
              placeholder="Contraseña"
            />
            <InputError message={errors.password} />
          </div>

          <div className="grid gap-2">
            <Label htmlFor="password_confirmation">
              Confirmar contraseña <span className="text-destructive">*</span>
            </Label>
            <PasswordInput
              id="password_confirmation"
              name="password_confirmation"
              value={data.password_confirmation}
              onChange={(e) => setData('password_confirmation', e.target.value)}
              required
              autoComplete="new-password"
              placeholder="Repetí tu contraseña"
            />
            <InputError message={errors.password_confirmation} />
          </div>

          <Button type="submit" className="mt-2 w-full" disabled={processing}>
            {processing && <Spinner />}
            Crear mi cuenta de cliente
          </Button>

          <div className="text-center text-sm text-muted-foreground">
            ¿Ya tenés una cuenta?{' '}
            <TextLink href="/login">Iniciar sesión</TextLink>
          </div>
        </div>
      </form>
    </>
  );
}

Register.layout = {
  title: 'Crear cuenta de cliente',
  description: 'Registrate para comprar online y gestionar tus pedidos',
};
