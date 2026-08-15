import { Form, Head, usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { Auth } from '@/types';

type PageProps = {
  auth: Auth;
};

export default function Profile({
  mustVerifyEmail,
  status,
}: {
  mustVerifyEmail: boolean;
  status?: string;
}) {
  const { auth } = usePage<PageProps>().props;

  return (
    <>
      <Head title="Configuración de perfil" />

      <h1 className="sr-only">Configuración de perfil</h1>

      <div className="space-y-6">
        <Heading
          variant="small"
          title="Perfil"
          description="Actualizá tu nombre y dirección de correo electrónico"
        />

        <Form
          {...ProfileController.update.form()}
          options={{
            preserveScroll: true,
          }}
          className="space-y-6"
        >
          {({ processing, errors }) => (
            <>
              <div className="grid gap-2">
                <Label htmlFor="name">Nombre</Label>

                <Input
                  id="name"
                  className="mt-1 block w-full"
                  defaultValue={auth.user.name}
                  name="name"
                  required
                  autoComplete="name"
                  placeholder="Nombre completo"
                />

                <InputError className="mt-2" message={errors.name} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="email">Correo electrónico</Label>

                <Input
                  id="email"
                  type="email"
                  className="mt-1 block w-full"
                  defaultValue={auth.user.email}
                  name="email"
                  required
                  autoComplete="username"
                  placeholder="Correo electrónico"
                />

                <InputError className="mt-2" message={errors.email} />
              </div>

              {mustVerifyEmail && auth.user.email_verified_at === null && (
                <div>
                  <p className="-mt-4 text-sm text-muted-foreground">
                    Tu dirección de correo no está verificada.{' '}
                    <Link
                      href={send()}
                      as="button"
                      className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                      Hacé clic acá para reenviar el correo de verificación.
                    </Link>
                  </p>

                  {status === 'verification-link-sent' && (
                    <div className="mt-2 text-sm font-medium text-green-600">
                      Se envió un nuevo enlace de verificación a tu dirección de
                      correo.
                    </div>
                  )}
                </div>
              )}

              <div className="flex items-center gap-4">
                <Button disabled={processing} data-test="update-profile-button">
                  Guardar
                </Button>
              </div>
            </>
          )}
        </Form>
      </div>

      <DeleteUser />
    </>
  );
}

Profile.layout = {
  breadcrumbs: [
    {
      title: 'Configuración de perfil',
      href: edit(),
    },
  ],
};
