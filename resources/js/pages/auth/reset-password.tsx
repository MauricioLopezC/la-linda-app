import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password';

type Props = {
  token: string;
  email: string;
  passwordRules: string;
};

export default function ResetPassword({ token, email, passwordRules }: Props) {
  return (
    <>
      <Head title="Restablecer contraseña" />

      <Form
        {...update.form()}
        transform={(data) => ({ ...data, token, email })}
        resetOnSuccess={['password', 'password_confirmation']}
      >
        {({ processing, errors }) => (
          <div className="grid gap-6">
            <div className="grid gap-2">
              <Label htmlFor="email">Correo electrónico</Label>
              <Input
                id="email"
                type="email"
                name="email"
                autoComplete="email"
                value={email}
                className="mt-1 block w-full"
                readOnly
              />
              <InputError message={errors.email} className="mt-2" />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="password">Contraseña</Label>
              <PasswordInput
                id="password"
                name="password"
                autoComplete="new-password"
                className="mt-1 block w-full"
                autoFocus
                placeholder="Contraseña"
                passwordrules={passwordRules}
              />
              <InputError message={errors.password} />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="password_confirmation">
                Confirmar contraseña
              </Label>
              <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                autoComplete="new-password"
                className="mt-1 block w-full"
                placeholder="Confirmar contraseña"
                passwordrules={passwordRules}
              />
              <InputError
                message={errors.password_confirmation}
                className="mt-2"
              />
            </div>

            <Button
              type="submit"
              className="mt-4 w-full"
              disabled={processing}
              data-test="reset-password-button"
            >
              {processing && <Spinner />}
              Restablecer contraseña
            </Button>
          </div>
        )}
      </Form>
    </>
  );
}

ResetPassword.layout = {
  title: 'Restablecer contraseña',
  description: 'Ingresá tu nueva contraseña a continuación',
};
