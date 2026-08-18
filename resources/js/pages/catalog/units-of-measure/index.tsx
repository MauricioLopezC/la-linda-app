import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Power, Search } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
  store,
  toggleStatus,
  update,
} from '@/actions/App/Http/Controllers/Catalog/UnitOfMeasureController';
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
import { index } from '@/routes/catalog/units-of-measure';
import type { BreadcrumbItem } from '@/types';

type UnitOfMeasure = App.Data.Catalog.UnitOfMeasureData;
type Props = { unitsOfMeasure: UnitOfMeasure[] };
type UnitFormData = { name: string; abbreviation: string; is_active: boolean };

export default function UnitsOfMeasureIndex({ unitsOfMeasure = [] }: Props) {
  const [searchTerm, setSearchTerm] = useState('');
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [editingUnit, setEditingUnit] = useState<UnitOfMeasure | null>(null);
  const createForm = useForm<UnitFormData>({
    name: '',
    abbreviation: '',
    is_active: true,
  });
  const editForm = useForm<UnitFormData>({
    name: '',
    abbreviation: '',
    is_active: true,
  });
  const normalizedSearch = searchTerm.trim().toLowerCase();
  const filteredUnits = unitsOfMeasure.filter(
    (unit) =>
      unit.name.toLowerCase().includes(normalizedSearch) ||
      unit.abbreviation.toLowerCase().includes(normalizedSearch),
  );

  const openCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    setIsCreateOpen(true);
  };

  const openEdit = (unit: UnitOfMeasure) => {
    editForm.setData({
      name: unit.name,
      abbreviation: unit.abbreviation,
      is_active: unit.is_active,
    });
    editForm.clearErrors();
    setEditingUnit(unit);
  };

  const submitCreate = (event: React.FormEvent) => {
    event.preventDefault();
    createForm.post(store.url(), {
      preserveScroll: true,
      onSuccess: () => {
        setIsCreateOpen(false);
        toast.success('Unidad de medida creada correctamente');
      },
      onError: () => toast.error('Revisá los datos de la unidad de medida'),
    });
  };

  const submitEdit = (event: React.FormEvent) => {
    event.preventDefault();

    if (!editingUnit) {
      return;
    }

    editForm.put(update.url({ unit_of_measure: editingUnit.id }), {
      preserveScroll: true,
      onSuccess: () => {
        setEditingUnit(null);
        toast.success('Unidad de medida actualizada correctamente');
      },
      onError: () => toast.error('Revisá los datos de la unidad de medida'),
    });
  };

  const toggle = (unit: UnitOfMeasure) => {
    router.patch(
      toggleStatus.url({ unit_of_measure: unit.id }),
      {},
      {
        preserveScroll: true,
        onSuccess: () => toast.success(`Estado de "${unit.name}" actualizado`),
        onError: (errors) =>
          toast.error(
            errors.unit_of_measure ??
              'No se pudo actualizar la unidad de medida',
          ),
      },
    );
  };

  const formFields = (form: typeof createForm, prefix: 'create' | 'edit') => (
    <div className="grid gap-4 py-4">
      <div className="grid gap-2">
        <Label htmlFor={`${prefix}-unit-name`}>Nombre *</Label>
        <Input
          id={`${prefix}-unit-name`}
          value={form.data.name}
          onChange={(event) => form.setData('name', event.target.value)}
          required
        />
        <InputError message={form.errors.name} />
      </div>
      <div className="grid gap-2">
        <Label htmlFor={`${prefix}-unit-abbreviation`}>Abreviatura *</Label>
        <Input
          id={`${prefix}-unit-abbreviation`}
          value={form.data.abbreviation}
          onChange={(event) => form.setData('abbreviation', event.target.value)}
          required
          maxLength={20}
        />
        <InputError message={form.errors.abbreviation} />
      </div>
      <div className="flex items-center gap-2">
        <Checkbox
          id={`${prefix}-unit-active`}
          checked={form.data.is_active}
          onCheckedChange={(checked) =>
            form.setData('is_active', checked === true)
          }
        />
        <Label htmlFor={`${prefix}-unit-active`}>Unidad activa</Label>
      </div>
      <InputError message={form.errors.is_active} />
    </div>
  );

  return (
    <>
      <Head title="Unidades de medida" />
      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <Heading
          title="Unidades de medida"
          description="Administrá los nombres y abreviaturas usados en el catálogo."
        />
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative max-w-sm flex-1">
            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder="Buscar por nombre o abreviatura..."
              value={searchTerm}
              onChange={(event) => setSearchTerm(event.target.value)}
              className="pl-9"
            />
          </div>
          <Button onClick={openCreate}>
            <Plus className="mr-1.5 size-4" />
            Nueva unidad
          </Button>
        </div>
        <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nombre</TableHead>
                <TableHead>Abreviatura</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredUnits.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={4}
                    className="py-12 text-center text-muted-foreground"
                  >
                    No se encontraron unidades de medida registradas.
                  </TableCell>
                </TableRow>
              ) : (
                filteredUnits.map((unit) => (
                  <TableRow key={unit.id}>
                    <TableCell className="font-medium">{unit.name}</TableCell>
                    <TableCell>
                      <span className="rounded bg-muted px-2 py-1 font-mono text-sm">
                        {unit.abbreviation}
                      </span>
                    </TableCell>
                    <TableCell>
                      <Badge variant={unit.is_active ? 'default' : 'secondary'}>
                        {unit.is_active ? 'Activa' : 'Inactiva'}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => openEdit(unit)}
                        aria-label={`Editar ${unit.name}`}
                      >
                        <Pencil className="size-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => toggle(unit)}
                        aria-label={`Cambiar estado de ${unit.name}`}
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
              <DialogTitle>Nueva unidad de medida</DialogTitle>
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
                Crear unidad
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
      <Dialog
        open={editingUnit !== null}
        onOpenChange={(open) => !open && setEditingUnit(null)}
      >
        <DialogContent className="sm:max-w-md">
          <form onSubmit={submitEdit}>
            <DialogHeader>
              <DialogTitle>Editar unidad de medida</DialogTitle>
            </DialogHeader>
            {formFields(editForm, 'edit')}
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setEditingUnit(null)}
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

UnitsOfMeasureIndex.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Unidades de medida', href: index() },
  ] satisfies BreadcrumbItem[],
};
