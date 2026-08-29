import { Head, router, useForm } from '@inertiajs/react';
import {
  ArrowDownCircle,
  ArrowUpCircle,
  Lock,
  Pencil,
  Plus,
  Search,
  Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
  destroyMovementType,
  storeMovementType,
  updateMovementType,
} from '@/actions/App/Http/Controllers/Inventory/StockParameterController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import { index } from '@/routes/inventory/parameters';
import type { BreadcrumbItem } from '@/types';

type StockMovementType = App.Data.Inventory.StockMovementTypeData;

type Props = {
  movementTypes: StockMovementType[];
};

export default function StockParametersIndex({ movementTypes = [] }: Props) {
  const [searchTerm, setSearchTerm] = useState('');

  const [isCreateTypeOpen, setIsCreateTypeOpen] = useState(false);
  const [editingType, setEditingType] = useState<StockMovementType | null>(
    null,
  );
  const [deletingType, setDeletingType] = useState<StockMovementType | null>(
    null,
  );

  const createTypeForm = useForm({
    name: '',
    sign: '1',
    description: '',
    is_active: true,
  });

  const editTypeForm = useForm({
    name: '',
    sign: '1',
    description: '',
    is_active: true,
  });

  const filteredTypes = movementTypes.filter(
    (type) =>
      type.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      type.code.toLowerCase().includes(searchTerm.toLowerCase()) ||
      (type.description &&
        type.description.toLowerCase().includes(searchTerm.toLowerCase())),
  );

  const handleOpenCreateType = () => {
    createTypeForm.reset();
    createTypeForm.clearErrors();
    setIsCreateTypeOpen(true);
  };

  const handleCreateTypeSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    createTypeForm.transform((data) => ({
      ...data,
      sign: Number(data.sign),
    }));
    createTypeForm.post(storeMovementType.url(), {
      preserveScroll: true,
      onSuccess: () => {
        setIsCreateTypeOpen(false);
        createTypeForm.reset();
        toast.success('Tipo de movimiento creado correctamente');
      },
      onError: () => {
        toast.error('Hubo un error al crear el tipo de movimiento');
      },
    });
  };

  const handleOpenEditType = (type: StockMovementType) => {
    setEditingType(type);
    editTypeForm.setData({
      name: type.name,
      sign: String(type.sign),
      description: type.description || '',
      is_active: type.is_active,
    });
    editTypeForm.clearErrors();
  };

  const handleEditTypeSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!editingType) {
      return;
    }

    editTypeForm.transform((data) => ({
      ...data,
      sign: Number(data.sign),
    }));

    editTypeForm.put(
      updateMovementType.url({ movement_type: editingType.id }),
      {
        preserveScroll: true,
        onSuccess: () => {
          setEditingType(null);
          toast.success('Tipo de movimiento actualizado');
        },
        onError: () => {
          toast.error('Error al actualizar el tipo de movimiento');
        },
      },
    );
  };

  const handleDeleteTypeConfirm = () => {
    if (!deletingType) {
      return;
    }

    router.delete(destroyMovementType.url({ movement_type: deletingType.id }), {
      preserveScroll: true,
      onSuccess: () => {
        setDeletingType(null);
        toast.success('Tipo de movimiento eliminado');
      },
      onError: (errors) => {
        toast.error(
          errors.movement_type ||
            'No se puede eliminar este tipo de movimiento',
        );
      },
    });
  };

  return (
    <>
      <Head title="Parámetros de Stock" />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <Heading
            title="Tipos de Movimiento de Stock"
            description="Cada tipo tipa un movimiento y define su signo de afectación (suma o resta). El nombre del tipo es la justificación que se ve en el historial del artículo."
          />
        </div>

        <div className="flex flex-col gap-4">
          {/* Action Bar (Search + Add) */}
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="relative max-w-sm flex-1">
              <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder="Buscar tipo por nombre, código o descripción..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-9"
              />
            </div>

            <Button
              onClick={handleOpenCreateType}
              className="bg-primary text-primary-foreground hover:bg-primary/90"
            >
              <Plus className="mr-1.5 size-4" />
              Nuevo Tipo de Movimiento
            </Button>
          </div>

          <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Nombre</TableHead>
                  <TableHead>Código</TableHead>
                  <TableHead>Signo / Afectación</TableHead>
                  <TableHead>Tipo</TableHead>
                  <TableHead>Descripción</TableHead>
                  <TableHead className="text-right">Acciones</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {filteredTypes.length === 0 ? (
                  <TableRow>
                    <TableCell
                      colSpan={6}
                      className="py-12 text-center text-muted-foreground"
                    >
                      No se encontraron tipos de movimiento registrados.
                    </TableCell>
                  </TableRow>
                ) : (
                  filteredTypes.map((type) => (
                    <TableRow key={type.id}>
                      <TableCell className="font-medium text-foreground">
                        {type.name}
                      </TableCell>
                      <TableCell className="font-mono text-xs text-muted-foreground">
                        {type.code}
                      </TableCell>
                      <TableCell>
                        {type.sign === 1 ? (
                          <Badge
                            variant="outline"
                            className="border-emerald-600/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"
                          >
                            <ArrowUpCircle className="mr-1 size-3" />
                            (+1) Suma / Ingreso
                          </Badge>
                        ) : (
                          <Badge
                            variant="outline"
                            className="border-amber-600/30 bg-amber-500/10 text-amber-700 dark:text-amber-400"
                          >
                            <ArrowDownCircle className="mr-1 size-3" />
                            (-1) Resta / Egreso
                          </Badge>
                        )}
                      </TableCell>
                      <TableCell>
                        {type.is_system ? (
                          <Badge
                            variant="secondary"
                            className="gap-1 font-normal"
                          >
                            <Lock className="size-3" />
                            Sistema
                          </Badge>
                        ) : (
                          <Badge
                            variant="outline"
                            className="text-muted-foreground"
                          >
                            Personalizado
                          </Badge>
                        )}
                      </TableCell>
                      <TableCell className="text-muted-foreground">
                        {type.description || '—'}
                      </TableCell>
                      <TableCell className="text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => handleOpenEditType(type)}
                            title="Editar tipo"
                          >
                            <Pencil className="size-4" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="icon"
                            disabled={type.is_system}
                            onClick={() => setDeletingType(type)}
                            title={
                              type.is_system
                                ? 'Los tipos del sistema no pueden eliminarse'
                                : 'Eliminar tipo'
                            }
                            className={
                              type.is_system
                                ? 'cursor-not-allowed opacity-40'
                                : 'text-destructive hover:bg-destructive/10 hover:text-destructive'
                            }
                          >
                            <Trash2 className="size-4" />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </div>
        </div>
      </div>

      {/* DIALOG: Create Movement Type */}
      <Dialog open={isCreateTypeOpen} onOpenChange={setIsCreateTypeOpen}>
        <DialogContent className="sm:max-w-md">
          <form onSubmit={handleCreateTypeSubmit}>
            <DialogHeader>
              <DialogTitle>Nuevo Tipo de Movimiento de Stock</DialogTitle>
              <DialogDescription>
                Creá un tipo descriptivo para tipar los movimientos de
                mercadería. Su signo queda fijo.
              </DialogDescription>
            </DialogHeader>

            <div className="grid gap-4 py-4">
              <div className="grid gap-2">
                <Label htmlFor="create-type-name">Nombre *</Label>
                <Input
                  id="create-type-name"
                  placeholder="Ej. Donación a institución, Consumo interno..."
                  value={createTypeForm.data.name}
                  onChange={(e) =>
                    createTypeForm.setData('name', e.target.value)
                  }
                  required
                />
                <InputError message={createTypeForm.errors.name} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="create-type-sign">
                  Signo de Afectación al Stock *
                </Label>
                <Select
                  value={createTypeForm.data.sign}
                  onValueChange={(val) => createTypeForm.setData('sign', val)}
                >
                  <SelectTrigger id="create-type-sign">
                    <SelectValue placeholder="Seleccioná el signo" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="1">
                      +1 : Suma / Ingreso de existencias
                    </SelectItem>
                    <SelectItem value="-1">
                      -1 : Resta / Egreso de existencias
                    </SelectItem>
                  </SelectContent>
                </Select>
                <InputError message={createTypeForm.errors.sign} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="create-type-desc">Descripción (opcional)</Label>
                <Input
                  id="create-type-desc"
                  placeholder="Descripción del movimiento..."
                  value={createTypeForm.data.description}
                  onChange={(e) =>
                    createTypeForm.setData('description', e.target.value)
                  }
                />
                <InputError message={createTypeForm.errors.description} />
              </div>
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setIsCreateTypeOpen(false)}
              >
                Cancelar
              </Button>
              <Button
                type="submit"
                disabled={createTypeForm.processing}
                className="bg-primary text-primary-foreground hover:bg-primary/90"
              >
                Guardar Tipo
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* DIALOG: Edit Movement Type */}
      <Dialog
        open={editingType !== null}
        onOpenChange={(open) => !open && setEditingType(null)}
      >
        <DialogContent className="sm:max-w-md">
          <form onSubmit={handleEditTypeSubmit}>
            <DialogHeader>
              <DialogTitle>Editar Tipo de Movimiento</DialogTitle>
              <DialogDescription>
                {editingType?.is_system
                  ? 'Este es un tipo propio del sistema. Su código y signo están protegidos para asegurar la integridad de las operaciones.'
                  : editingType?.is_in_use
                    ? 'Este tipo ya tiene movimientos registrados, así que su signo queda fijo: cambiarlo haría ilegible el historial.'
                    : 'Modificá la configuración del tipo de movimiento.'}
              </DialogDescription>
            </DialogHeader>

            <div className="grid gap-4 py-4">
              <div className="grid gap-2">
                <Label htmlFor="edit-type-name">Nombre *</Label>
                <Input
                  id="edit-type-name"
                  value={editTypeForm.data.name}
                  onChange={(e) => editTypeForm.setData('name', e.target.value)}
                  required
                />
                <InputError message={editTypeForm.errors.name} />
              </div>

              {!editingType?.is_system && !editingType?.is_in_use && (
                <div className="grid gap-2">
                  <Label htmlFor="edit-type-sign">
                    Signo de Afectación al Stock *
                  </Label>
                  <Select
                    value={editTypeForm.data.sign}
                    onValueChange={(val) => editTypeForm.setData('sign', val)}
                  >
                    <SelectTrigger id="edit-type-sign">
                      <SelectValue placeholder="Seleccioná el signo" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="1">
                        +1 : Suma / Ingreso de existencias
                      </SelectItem>
                      <SelectItem value="-1">
                        -1 : Resta / Egreso de existencias
                      </SelectItem>
                    </SelectContent>
                  </Select>
                  <InputError message={editTypeForm.errors.sign} />
                </div>
              )}

              <div className="grid gap-2">
                <Label htmlFor="edit-type-desc">Descripción</Label>
                <Input
                  id="edit-type-desc"
                  value={editTypeForm.data.description}
                  onChange={(e) =>
                    editTypeForm.setData('description', e.target.value)
                  }
                />
                <InputError message={editTypeForm.errors.description} />
              </div>
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setEditingType(null)}
              >
                Cancelar
              </Button>
              <Button
                type="submit"
                disabled={editTypeForm.processing}
                className="bg-primary text-primary-foreground hover:bg-primary/90"
              >
                Guardar Cambios
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* DIALOG: Delete Movement Type Confirmation */}
      <Dialog
        open={deletingType !== null}
        onOpenChange={(open) => !open && setDeletingType(null)}
      >
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>¿Eliminar tipo de movimiento?</DialogTitle>
            <DialogDescription>
              Esta acción eliminará el tipo "
              <span className="font-semibold text-foreground">
                {deletingType?.name}
              </span>
              ". No se podrá eliminar si ya tiene movimientos registrados.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter className="gap-2 sm:gap-0">
            <Button
              type="button"
              variant="outline"
              onClick={() => setDeletingType(null)}
            >
              Cancelar
            </Button>
            <Button
              type="button"
              variant="destructive"
              onClick={handleDeleteTypeConfirm}
            >
              Confirmar Eliminación
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

StockParametersIndex.layout = {
  breadcrumbs: [
    {
      title: 'Dashboard',
      href: dashboard(),
    },
    {
      title: 'Parámetros de Stock',
      href: index(),
    },
  ] satisfies BreadcrumbItem[],
};
