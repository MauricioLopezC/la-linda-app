import { Head, Link, router } from '@inertiajs/react';
import {
  AlertCircle,
  ArrowLeft,
  CheckCircle2,
  Clock,
  Receipt,
  RotateCcw,
} from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { index, pay, show } from '@/routes/tienda/orders';
import type { BreadcrumbItem } from '@/types';

type WebOrder = App.Data.Ecommerce.WebOrderData;

type Props = {
  order: WebOrder;
  status: 'approved' | 'pending' | 'rejected';
};

export default function OrderPaymentReturn({ order, status }: Props) {
  const [isRetrying, setIsRetrying] = useState(false);

  const handleRetryPayment = () => {
    setIsRetrying(true);
    router.post(
      pay.url(order.id),
      {},
      {
        onFinish: () => setIsRetrying(false),
      },
    );
  };

  const isPaid = order.status === 'pagado';
  const isRejected = !isPaid && status === 'rejected';
  const isPending = !isPaid && !isRejected;

  return (
    <>
      <Head title={`Retorno de pago | Pedido N.º ${order.formatted_number}`} />

      <div className="flex flex-1 flex-col items-center justify-center p-4 md:p-8">
        <div className="w-full max-w-xl space-y-6">
          <Card className="border-border text-center shadow-sm">
            <CardHeader className="flex flex-col items-center pt-8 pb-4">
              {isPaid && (
                <div className="mb-4 flex size-16 items-center justify-center rounded-full bg-success-bg text-success-fg">
                  <CheckCircle2 className="size-10" />
                </div>
              )}
              {isPending && (
                <div className="mb-4 flex size-16 items-center justify-center rounded-full bg-warning-bg text-warning-fg">
                  <Clock className="size-10" />
                </div>
              )}
              {isRejected && (
                <div className="mb-4 flex size-16 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                  <AlertCircle className="size-10" />
                </div>
              )}

              <CardTitle className="text-2xl font-bold tracking-tight text-foreground">
                {isPaid && '¡Pago recibido!'}
                {isPending && 'Pago en procesamiento'}
                {isRejected && 'No pudimos procesar el pago'}
              </CardTitle>

              <CardDescription className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
                {isPaid &&
                  'Tu pago fue recibido y acreditado correctamente. Ya podés consultar el estado de tu pedido.'}
                {isPending &&
                  (status === 'approved'
                    ? 'Mercado Pago está confirmando la transacción. Apenas se acredite, vas a ver tu pedido actualizado en tu cuenta.'
                    : 'Tu pago se encuentra pendiente de acreditación por parte de Mercado Pago. Te notificaremos y actualizaremos tu pedido apenas se confirme.')}
                {isRejected &&
                  'La operación fue cancelada o rechazada por el medio de pago. Tu pedido sigue guardado como pendiente para que puedas reintentar.'}
              </CardDescription>
            </CardHeader>

            <CardContent className="space-y-4 px-6 pb-6 text-left">
              <div className="space-y-2.5 rounded-lg border border-border bg-muted/40 p-4 text-sm">
                <div className="flex items-center justify-between">
                  <span className="text-muted-foreground">Pedido</span>
                  <span className="font-semibold text-foreground tabular-nums">
                    N.º {order.formatted_number}
                  </span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-muted-foreground">Modalidad</span>
                  <span className="font-medium text-foreground">
                    {order.delivery_method_label}
                  </span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-muted-foreground">
                    Estado del pedido
                  </span>
                  <Badge
                    variant={
                      order.status === 'pagado' ? 'default' : 'secondary'
                    }
                    className="text-xs"
                  >
                    {order.status_label}
                  </Badge>
                </div>
                <Separator />
                <div className="flex items-center justify-between text-base">
                  <span className="font-bold text-foreground">Total</span>
                  <span className="font-extrabold text-primary tabular-nums">
                    {order.formatted_total_amount}
                  </span>
                </div>
              </div>
            </CardContent>

            <CardFooter className="flex flex-col gap-2.5 px-6 pb-8 sm:flex-row sm:justify-center">
              {isRejected && (
                <Button
                  onClick={handleRetryPayment}
                  disabled={isRetrying}
                  className="w-full gap-2 font-semibold shadow-xs sm:w-auto"
                >
                  <RotateCcw className="size-4" />
                  {isRetrying
                    ? 'Conectando...'
                    : 'Reintentar pago con Mercado Pago'}
                </Button>
              )}

              <Button
                variant="outline"
                asChild
                className="w-full gap-2 sm:w-auto"
              >
                <Link href={show.url(order.id)}>
                  <Receipt className="size-4" />
                  Ver detalle del pedido
                </Link>
              </Button>

              <Button
                variant="ghost"
                asChild
                className="w-full gap-2 sm:w-auto"
              >
                <Link href={index.url()}>
                  <ArrowLeft className="size-4" />
                  Mis pedidos
                </Link>
              </Button>
            </CardFooter>
          </Card>
        </div>
      </div>
    </>
  );
}

OrderPaymentReturn.layout = {
  breadcrumbs: [
    {
      title: 'Tienda Online',
      href: '/tienda',
    },
    {
      title: 'Mis pedidos',
      href: '/tienda/mis-pedidos',
    },
    {
      title: 'Retorno de pago',
      href: '#',
    },
  ] satisfies BreadcrumbItem[],
};
