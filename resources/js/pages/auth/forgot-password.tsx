// Components
import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
  return (
    <>
      <Head title="Recuperar contraseña" />

      {status && (
        <div className="mb-4 text-center text-sm font-medium text-green-600">
          {status}
        </div>
      )}

      <div className="space-y-6">
        <Form {...email.form()}>
          {({ processing, errors }) => (
            <>
              <div className="grid gap-2">
                <Label htmlFor="email">Correo electrónico</Label>
                <Input
                  id="email"
                  type="email"
                  name="email"
                  autoComplete="off"
                  autoFocus
                  placeholder="correo@ejemplo.com"
                />

                <InputError message={errors.email} />
              </div>

              <div className="my-6 flex items-center justify-start">
                <Button
                  className="w-full"
                  disabled={processing}
                  data-test="email-password-reset-link-button"
                >
                  {processing && (
                    <LoaderCircle className="h-4 w-4 animate-spin" />
                  )}
                  Enviar enlace de restablecimiento
                </Button>
              </div>
            </>
          )}
        </Form>

        <div className="space-x-1 text-center text-sm text-muted-foreground">
          <span>O volvé a</span>
          <TextLink href={login()}>iniciar sesión</TextLink>
        </div>
      </div>
    </>
  );
}

ForgotPassword.layout = {
  title: 'Recuperar contraseña',
  description:
    'Ingresá tu correo electrónico para recibir un enlace de restablecimiento de contraseña',
};
