import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Power, Search } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
  store,
  toggleStatus,
  update,
} from '@/actions/App/Http/Controllers/Pricing/VatRateController';
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
import { index } from '@/routes/pricing/vat-rates';
import type { BreadcrumbItem } from '@/types';

type VatRate = App.Data.Pricing.VatRateData;
type Props = { vatRates: VatRate[] };
type VatRateFormData = {
  description: string;
  percentage: string;
  is_active: boolean;
};

const formatPercentage = (percentage: number) =>
  `${percentage.toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} %`;

export default function VatRatesIndex({ vatRates = [] }: Props) {
  const [searchTerm, setSearchTerm] = useState('');
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [editingVatRate, setEditingVatRate] = useState<VatRate | null>(null);
  const createForm = useForm<VatRateFormData>({
    description: '',
    percentage: '',
    is_active: true,
  });
  const editForm = useForm<VatRateFormData>({
    description: '',
    percentage: '',
    is_active: true,
  });
  const filteredVatRates = vatRates.filter((vatRate) =>
    vatRate.description.toLowerCase().includes(searchTerm.trim().toLowerCase()),
  );

  const openCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    setIsCreateOpen(true);
  };

  const openEdit = (vatRate: VatRate) => {
    editForm.setData({
      description: vatRate.description,
      percentage: String(vatRate.percentage),
      is_active: vatRate.is_active,
    });
    editForm.clearErrors();
    setEditingVatRate(vatRate);
  };

  const submitCreate = (event: React.FormEvent) => {
    event.preventDefault();
    createForm.post(store.url(), {
      preserveScroll: true,
      onSuccess: () => {
        setIsCreateOpen(false);
        toast.success('Alícuota de IVA creada correctamente');
      },
      onError: () => toast.error('Revisá los datos de la alícuota'),
    });
  };

  const submitEdit = (event: React.FormEvent) => {
    event.preventDefault();

    if (!editingVatRate) {
      return;
    }

    editForm.put(update.url({ vat_rate: editingVatRate.id }), {
      preserveScroll: true,
      onSuccess: () => {
        setEditingVatRate(null);
        toast.success('Alícuota de IVA actualizada correctamente');
      },
      onError: () => toast.error('Revisá los datos de la alícuota'),
    });
  };

  const toggle = (vatRate: VatRate) => {
    router.patch(
      toggleStatus.url({ vat_rate: vatRate.id }),
      {},
      {
        preserveScroll: true,
        onSuccess: () =>
          toast.success(`Estado de "${vatRate.description}" actualizado`),
        onError: (errors) =>
          toast.error(errors.vat_rate ?? 'No se pudo actualizar la alícuota'),
      },
    );
  };

  const formFields = (form: typeof createForm, prefix: 'create' | 'edit') => (
    <div className="grid gap-4 py-4">
      <div className="grid gap-2">
        <Label htmlFor={`${prefix}-vat-rate-description`}>Descripción *</Label>
        <Input
          id={`${prefix}-vat-rate-description`}
          value={form.data.description}
          onChange={(event) => form.setData('description', event.target.value)}
          required
        />
        <InputError message={form.errors.description} />
      </div>
      <div className="grid gap-2">
        <Label htmlFor={`${prefix}-vat-rate-percentage`}>
          Porcentaje (%) *
        </Label>
        <Input
          id={`${prefix}-vat-rate-percentage`}
          type="number"
          min={0}
          max={100}
          step={0.01}
          value={form.data.percentage}
          onChange={(event) => form.setData('percentage', event.target.value)}
          required
        />
        <InputError message={form.errors.percentage} />
      </div>
      <div className="flex items-center gap-2">
        <Checkbox
          id={`${prefix}-vat-rate-active`}
          checked={form.data.is_active}
          onCheckedChange={(checked) =>
            form.setData('is_active', checked === true)
          }
        />
        <Label htmlFor={`${prefix}-vat-rate-active`}>Alícuota activa</Label>
      </div>
      <InputError message={form.errors.is_active} />
    </div>
  );

  return (
    <>
      <Head title="Alícuotas de IVA" />
      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <Heading
          title="Alícuotas de IVA"
          description="Administrá las alícuotas de IVA disponibles para las operaciones."
        />
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative max-w-sm flex-1">
            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder="Buscar alícuota..."
              value={searchTerm}
              onChange={(event) => setSearchTerm(event.target.value)}
              className="pl-9"
            />
          </div>
          <Button onClick={openCreate}>
            <Plus className="mr-1.5 size-4" />
            Nueva alícuota
          </Button>
        </div>
        <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Descripción</TableHead>
                <TableHead>Porcentaje</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredVatRates.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={4}
                    className="py-12 text-center text-muted-foreground"
                  >
                    No se encontraron alícuotas registradas.
                  </TableCell>
                </TableRow>
              ) : (
                filteredVatRates.map((vatRate) => (
                  <TableRow key={vatRate.id}>
                    <TableCell className="font-medium">
                      {vatRate.description}
                    </TableCell>
                    <TableCell>
                      {formatPercentage(vatRate.percentage)}
                    </TableCell>
                    <TableCell>
                      <Badge
                        variant={vatRate.is_active ? 'default' : 'secondary'}
                      >
                        {vatRate.is_active ? 'Activa' : 'Inactiva'}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => openEdit(vatRate)}
                        aria-label={`Editar ${vatRate.description}`}
                      >
                        <Pencil className="size-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => toggle(vatRate)}
                        aria-label={`Cambiar estado de ${vatRate.description}`}
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
              <DialogTitle>Nueva alícuota</DialogTitle>
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
                Crear alícuota
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
      <Dialog
        open={editingVatRate !== null}
        onOpenChange={(open) => !open && setEditingVatRate(null)}
      >
        <DialogContent className="sm:max-w-md">
          <form onSubmit={submitEdit}>
            <DialogHeader>
              <DialogTitle>Editar alícuota</DialogTitle>
            </DialogHeader>
            {formFields(editForm, 'edit')}
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setEditingVatRate(null)}
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

VatRatesIndex.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Alícuotas de IVA', href: index() },
  ] satisfies BreadcrumbItem[],
};
