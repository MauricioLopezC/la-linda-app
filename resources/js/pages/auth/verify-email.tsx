// Components
import { Form, Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
  return (
    <>
      <Head title="Verificación de correo" />

      {status === 'verification-link-sent' && (
        <div className="mb-4 text-center text-sm font-medium text-green-600">
          Se envió un nuevo enlace de verificación a la dirección de correo que
          proporcionaste al registrarte.
        </div>
      )}

      <Form {...send.form()} className="space-y-6 text-center">
        {({ processing }) => (
          <>
            <Button disabled={processing} variant="secondary">
              {processing && <Spinner />}
              Reenviar correo de verificación
            </Button>

            <TextLink href={logout()} className="mx-auto block text-sm">
              Cerrar sesión
            </TextLink>
          </>
        )}
      </Form>
    </>
  );
}

VerifyEmail.layout = {
  title: 'Verificación de correo',
  description:
    'Por favor verificá tu dirección de correo haciendo clic en el enlace que te acabamos de enviar.',
};
