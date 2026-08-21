import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Power, Search } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
  store,
  toggleStatus,
  update,
} from '@/actions/App/Http/Controllers/Sales/PaymentMethodController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import { index } from '@/routes/sales/payment-methods';
import type { BreadcrumbItem } from '@/types';

type PaymentMethod = App.Data.Sales.PaymentMethodData;
type Props = { paymentMethods: PaymentMethod[] };
type PaymentMethodFormData = {
  name: string;
  is_enabled_online: boolean;
  is_active: boolean;
};

export default function PaymentMethodsIndex({ paymentMethods = [] }: Props) {
  const [searchTerm, setSearchTerm] = useState('');
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [editingPaymentMethod, setEditingPaymentMethod] =
    useState<PaymentMethod | null>(null);
  const createForm = useForm<PaymentMethodFormData>({
    name: '',
    is_enabled_online: false,
    is_active: true,
  });
  const editForm = useForm<PaymentMethodFormData>({
    name: '',
    is_enabled_online: false,
    is_active: true,
  });
  const filteredPaymentMethods = paymentMethods.filter((paymentMethod) =>
    paymentMethod.name.toLowerCase().includes(searchTerm.trim().toLowerCase()),
  );

  const openCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    setIsCreateOpen(true);
  };

  const openEdit = (paymentMethod: PaymentMethod) => {
    editForm.setData({
      name: paymentMethod.name,
      is_enabled_online: paymentMethod.is_enabled_online,
      is_active: paymentMethod.is_active,
    });
    editForm.clearErrors();
    setEditingPaymentMethod(paymentMethod);
  };

  const submitCreate = (event: React.FormEvent) => {
    event.preventDefault();
    createForm.post(store.url(), {
      preserveScroll: true,
      onSuccess: () => {
        setIsCreateOpen(false);
        toast.success('Medio de pago creado correctamente');
      },
      onError: () => toast.error('Revisá los datos del medio de pago'),
    });
  };

  const submitEdit = (event: React.FormEvent) => {
    event.preventDefault();

    if (!editingPaymentMethod) {
      return;
    }

    editForm.put(update.url({ payment_method: editingPaymentMethod.id }), {
      preserveScroll: true,
      onSuccess: () => {
        setEditingPaymentMethod(null);
        toast.success('Medio de pago actualizado correctamente');
      },
      onError: () => toast.error('Revisá los datos del medio de pago'),
    });
  };

  const toggle = (paymentMethod: PaymentMethod) => {
    router.patch(
      toggleStatus.url({ payment_method: paymentMethod.id }),
      {},
      {
        preserveScroll: true,
        onSuccess: () =>
          toast.success(`Estado de "${paymentMethod.name}" actualizado`),
        onError: (errors) =>
          toast.error(
            errors.payment_method ?? 'No se pudo actualizar el medio de pago',
          ),
      },
    );
  };

  const formFields = (form: typeof createForm, prefix: 'create' | 'edit') => (
    <div className="grid gap-4 py-4">
      <div className="grid gap-2">
        <Label htmlFor={`${prefix}-payment-method-name`}>Nombre *</Label>
        <Input
          id={`${prefix}-payment-method-name`}
          value={form.data.name}
          onChange={(event) => form.setData('name', event.target.value)}
          required
        />
        <InputError message={form.errors.name} />
      </div>
      <div className="flex items-center gap-2">
        <Checkbox
          id={`${prefix}-payment-method-online`}
          checked={form.data.is_enabled_online}
          onCheckedChange={(checked) =>
            form.setData('is_enabled_online', checked === true)
          }
        />
        <Label htmlFor={`${prefix}-payment-method-online`}>
          Habilitado en canal online
        </Label>
      </div>
      <InputError message={form.errors.is_enabled_online} />
      <div className="flex items-center gap-2">
        <Checkbox
          id={`${prefix}-payment-method-active`}
          checked={form.data.is_active}
          onCheckedChange={(checked) =>
            form.setData('is_active', checked === true)
          }
        />
        <Label htmlFor={`${prefix}-payment-method-active`}>
          Medio de pago activo
        </Label>
      </div>
      <InputError message={form.errors.is_active} />
    </div>
  );

  return (
    <>
      <Head title="Medios de Pago" />
      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <Heading
          title="Medios de Pago"
          description="Administrá los medios de pago disponibles para las operaciones."
        />
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative max-w-sm flex-1">
            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder="Buscar medio de pago..."
              value={searchTerm}
              onChange={(event) => setSearchTerm(event.target.value)}
              className="pl-9"
            />
          </div>
          <Button onClick={openCreate}>
            <Plus className="mr-1.5 size-4" />
            Nuevo medio de pago
          </Button>
        </div>
        <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nombre</TableHead>
                <TableHead>Canal online</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredPaymentMethods.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={4}
                    className="py-12 text-center text-muted-foreground"
                  >
                    No se encontraron medios de pago registrados.
                  </TableCell>
                </TableRow>
              ) : (
                filteredPaymentMethods.map((paymentMethod) => (
                  <TableRow key={paymentMethod.id}>
                    <TableCell className="font-medium">
                      {paymentMethod.name}
                    </TableCell>
                    <TableCell>
                      <Badge
                        variant={
                          paymentMethod.is_enabled_online
                            ? 'default'
                            : 'secondary'
                        }
                      >
                        {paymentMethod.is_enabled_online
                          ? 'Habilitado'
                          : 'No habilitado'}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <Badge
                        variant={
                          paymentMethod.is_active ? 'default' : 'secondary'
                        }
                      >
                        {paymentMethod.is_active ? 'Activo' : 'Inactivo'}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => openEdit(paymentMethod)}
                        aria-label={`Editar ${paymentMethod.name}`}
                      >
                        <Pencil className="size-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => toggle(paymentMethod)}
                        aria-label={`Cambiar estado de ${paymentMethod.name}`}
                      >
                        <Power className="size-4" />
                      </Button>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      </div>
      <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
        <DialogContent className="sm:max-w-md">
          <form onSubmit={submitCreate}>
            <DialogHeader>
              <DialogTitle>Nuevo medio de pago</DialogTitle>
            </DialogHeader>
            {formFields(createForm, 'create')}
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setIsCreateOpen(false)}
              >
                Cancelar
              </Button>
              <Button type="submit" disabled={createForm.processing}>
                Crear medio de pago
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
      <Dialog
        open={editingPaymentMethod !== null}
        onOpenChange={(open) => !open && setEditingPaymentMethod(null)}
      >
        <DialogContent className="sm:max-w-md">
          <form onSubmit={submitEdit}>
            <DialogHeader>
              <DialogTitle>Editar medio de pago</DialogTitle>
            </DialogHeader>
            {formFields(editForm, 'edit')}
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setEditingPaymentMethod(null)}
              >
                Cancelar
              </Button>
              <Button type="submit" disabled={editForm.processing}>
                Guardar cambios
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}

PaymentMethodsIndex.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Medios de Pago', href: index() },
  ] satisfies BreadcrumbItem[],
};
