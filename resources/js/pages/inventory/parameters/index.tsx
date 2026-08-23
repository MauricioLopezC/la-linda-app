import { Head, router, useForm } from '@inertiajs/react';
import {
  ArrowDownCircle,
  ArrowUpCircle,
  CheckCircle2,
  Lock,
  Pencil,
  Plus,
  Search,
  Sliders,
  Trash2,
  XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
  destroyAdjustmentReason,
  destroyMovementType,
  storeAdjustmentReason,
  storeMovementType,
  toggleAdjustmentReason,
  updateAdjustmentReason,
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
type StockAdjustmentReason = App.Data.Inventory.StockAdjustmentReasonData;

type Props = {
  movementTypes: StockMovementType[];
  adjustmentReasons: StockAdjustmentReason[];
};

export default function StockParametersIndex({
  movementTypes = [],
  adjustmentReasons = [],
}: Props) {
  const [activeTab, setActiveTab] = useState<'reasons' | 'movement_types'>(
    'reasons',
  );
  const [searchTerm, setSearchTerm] = useState('');

  // Dialog states for Stock Adjustment Reasons
  const [isCreateReasonOpen, setIsCreateReasonOpen] = useState(false);
  const [editingReason, setEditingReason] =
    useState<StockAdjustmentReason | null>(null);
  const [deletingReason, setDeletingReason] =
    useState<StockAdjustmentReason | null>(null);

  // Dialog states for Stock Movement Types
  const [isCreateTypeOpen, setIsCreateTypeOpen] = useState(false);
  const [editingType, setEditingType] = useState<StockMovementType | null>(
    null,
  );
  const [deletingType, setDeletingType] = useState<StockMovementType | null>(
    null,
  );

  // Form for creating adjustment reason
  const createReasonForm = useForm({
    name: '',
    description: '',
    is_active: true,
  });

  // Form for editing adjustment reason
  const editReasonForm = useForm({
    name: '',
    description: '',
    is_active: true,
  });

  // Form for creating movement type
  const createTypeForm = useForm({
    name: '',
    sign: '1',
    description: '',
    is_active: true,
  });

  // Form for editing movement type
  const editTypeForm = useForm({
    name: '',
    sign: '1',
    description: '',
    is_active: true,
  });

  // Filtered lists
  const filteredReasons = adjustmentReasons.filter(
    (reason) =>
      reason.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      (reason.description &&
        reason.description.toLowerCase().includes(searchTerm.toLowerCase())),
  );

  const filteredTypes = movementTypes.filter(
    (type) =>
      type.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      type.code.toLowerCase().includes(searchTerm.toLowerCase()) ||
      (type.description &&
        type.description.toLowerCase().includes(searchTerm.toLowerCase())),
  );

  // Handlers for Reasons
  const handleOpenCreateReason = () => {
    createReasonForm.reset();
    createReasonForm.clearErrors();
    setIsCreateReasonOpen(true);
  };

  const handleCreateReasonSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    createReasonForm.post(storeAdjustmentReason.url(), {
      preserveScroll: true,
      onSuccess: () => {
        setIsCreateReasonOpen(false);
        createReasonForm.reset();
        toast.success('Motivo de ajuste creado correctamente');
      },
      onError: () => {
        toast.error('Hubo un error al crear el motivo');
      },
    });
  };

  const handleOpenEditReason = (reason: StockAdjustmentReason) => {
    setEditingReason(reason);
    editReasonForm.setData({
      name: reason.name,
      description: reason.description || '',
      is_active: reason.is_active,
    });
    editReasonForm.clearErrors();
  };

  const handleEditReasonSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!editingReason) {
      return;
    }

    editReasonForm.put(
      updateAdjustmentReason.url({ adjustment_reason: editingReason.id }),
      {
        preserveScroll: true,
        onSuccess: () => {
          setEditingReason(null);
          toast.success('Motivo de ajuste actualizado');
        },
        onError: () => {
          toast.error('Error al actualizar el motivo');
        },
      },
    );
  };

  const handleToggleReasonStatus = (reason: StockAdjustmentReason) => {
    router.patch(
      toggleAdjustmentReason.url({ adjustment_reason: reason.id }),
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(`Motivo "${reason.name}" actualizado`);
        },
      },
    );
  };

  const handleDeleteReasonConfirm = () => {
    if (!deletingReason) {
      return;
    }

    router.delete(
      destroyAdjustmentReason.url({ adjustment_reason: deletingReason.id }),
      {
        preserveScroll: true,
        onSuccess: () => {
          setDeletingReason(null);
          toast.success('Motivo eliminado correctamente');
        },
        onError: (errors) => {
          toast.error(
            errors.reason ||
              'No se pudo eliminar el motivo porque ya está en uso',
          );
        },
      },
    );
  };

  // Handlers for Movement Types
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
        {/* Header */}
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <Heading
            title="Parámetros de Stock"
            description="Administrá los motivos de ajuste documentados y los tipos de movimientos del sistema."
          />
        </div>

        {/* Tab Navigation */}
        <div className="flex flex-col gap-4">
          <div className="flex border-b border-sidebar-border/80">
            <button
              type="button"
              onClick={() => {
                setActiveTab('reasons');
                setSearchTerm('');
              }}
              className={`flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold transition-colors ${
                activeTab === 'reasons'
                  ? 'border-primary text-primary'
                  : 'border-transparent text-muted-foreground hover:text-foreground'
              }`}
            >
              <Sliders className="size-4" />
              Motivos de Ajuste
              <Badge variant="secondary" className="ml-1 rounded-full px-2">
                {adjustmentReasons.length}
              </Badge>
            </button>

            <button
              type="button"
              onClick={() => {
                setActiveTab('movement_types');
                setSearchTerm('');
              }}
              className={`flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold transition-colors ${
                activeTab === 'movement_types'
                  ? 'border-primary text-primary'
                  : 'border-transparent text-muted-foreground hover:text-foreground'
              }`}
            >
              <ArrowUpCircle className="size-4" />
              Tipos de Movimiento
              <Badge variant="secondary" className="ml-1 rounded-full px-2">
                {movementTypes.length}
              </Badge>
            </button>
          </div>

          {/* Action Bar (Search + Add) */}
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="relative max-w-sm flex-1">
              <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder={
                  activeTab === 'reasons'
                    ? 'Buscar motivo por nombre o descripción...'
                    : 'Buscar tipo por nombre, código o descripción...'
                }
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-9"
              />
            </div>

            {activeTab === 'reasons' ? (
              <Button
                onClick={handleOpenCreateReason}
                className="bg-primary text-primary-foreground hover:bg-primary/90"
              >
                <Plus className="mr-1.5 size-4" />
                Nuevo Motivo de Ajuste
              </Button>
            ) : (
              <Button
                onClick={handleOpenCreateType}
                className="bg-primary text-primary-foreground hover:bg-primary/90"
              >
                <Plus className="mr-1.5 size-4" />
                Nuevo Tipo de Movimiento
              </Button>
            )}
          </div>

          {/* TAB 1: Motivos de Ajuste */}
          {activeTab === 'reasons' && (
            <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Nombre del Motivo</TableHead>
                    <TableHead>Descripción</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead>Fecha de Alta</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {filteredReasons.length === 0 ? (
                    <TableRow>
                      <TableCell
                        colSpan={5}
                        className="py-12 text-center text-muted-foreground"
                      >
                        No se encontraron motivos de ajuste registrados.
                      </TableCell>
                    </TableRow>
                  ) : (
                    filteredReasons.map((reason) => (
                      <TableRow key={reason.id}>
                        <TableCell className="font-medium text-foreground">
                          {reason.name}
                        </TableCell>
                        <TableCell className="text-muted-foreground">
                          {reason.description || '—'}
                        </TableCell>
                        <TableCell>
                          {reason.is_active ? (
                            <Badge
                              variant="outline"
                              className="border-emerald-600/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400"
                            >
                              <CheckCircle2 className="mr-1 size-3" />
                              Activo
                            </Badge>
                          ) : (
                            <Badge
                              variant="outline"
                              className="border-neutral-400/30 bg-neutral-500/10 text-neutral-600 dark:text-neutral-400"
                            >
                              <XCircle className="mr-1 size-3" />
                              Inactivo
                            </Badge>
                          )}
                        </TableCell>
                        <TableCell className="text-muted-foreground">
                          {reason.created_at || '—'}
                        </TableCell>
                        <TableCell className="text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => handleToggleReasonStatus(reason)}
                              title={
                                reason.is_active ? 'Desactivar' : 'Activar'
                              }
                              className="text-xs"
                            >
                              {reason.is_active ? 'Desactivar' : 'Activar'}
                            </Button>
                            <Button
                              variant="ghost"
                              size="icon"
                              onClick={() => handleOpenEditReason(reason)}
                              title="Editar motivo"
                            >
                              <Pencil className="size-4" />
                            </Button>
                            <Button
                              variant="ghost"
                              size="icon"
                              onClick={() => setDeletingReason(reason)}
                              title="Eliminar motivo"
                              className="text-destructive hover:bg-destructive/10 hover:text-destructive"
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
          )}

          {/* TAB 2: Tipos de Movimiento */}
          {activeTab === 'movement_types' && (
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
          )}
        </div>
      </div>

      {/* DIALOG: Create Adjustment Reason */}
      <Dialog open={isCreateReasonOpen} onOpenChange={setIsCreateReasonOpen}>
        <DialogContent className="sm:max-w-md">
          <form onSubmit={handleCreateReasonSubmit}>
            <DialogHeader>
              <DialogTitle>Nuevo Motivo de Ajuste</DialogTitle>
              <DialogDescription>
                Registrá un motivo controlado que justifique los ajustes de
                existencias en depósito.
              </DialogDescription>
            </DialogHeader>

            <div className="grid gap-4 py-4">
              <div className="grid gap-2">
                <Label htmlFor="create-reason-name">Nombre del Motivo *</Label>
                <Input
                  id="create-reason-name"
                  placeholder="Ej. Rotura / Daño, Vencimiento..."
                  value={createReasonForm.data.name}
                  onChange={(e) =>
                    createReasonForm.setData('name', e.target.value)
                  }
                  required
                />
                <InputError message={createReasonForm.errors.name} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="create-reason-desc">
                  Descripción (opcional)
                </Label>
                <Input
                  id="create-reason-desc"
                  placeholder="Detalles sobre cuándo aplicar este motivo"
                  value={createReasonForm.data.description}
                  onChange={(e) =>
                    createReasonForm.setData('description', e.target.value)
                  }
                />
                <InputError message={createReasonForm.errors.description} />
              </div>
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setIsCreateReasonOpen(false)}
              >
                Cancelar
              </Button>
              <Button
                type="submit"
                disabled={createReasonForm.processing}
                className="bg-primary text-primary-foreground hover:bg-primary/90"
              >
                Guardar Motivo
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* DIALOG: Edit Adjustment Reason */}
      <Dialog
        open={editingReason !== null}
        onOpenChange={(open) => !open && setEditingReason(null)}
      >
        <DialogContent className="sm:max-w-md">
          <form onSubmit={handleEditReasonSubmit}>
            <DialogHeader>
              <DialogTitle>Editar Motivo de Ajuste</DialogTitle>
              <DialogDescription>
                Modificá los datos del motivo de ajuste de existencias.
              </DialogDescription>
            </DialogHeader>

            <div className="grid gap-4 py-4">
              <div className="grid gap-2">
                <Label htmlFor="edit-reason-name">Nombre del Motivo *</Label>
                <Input
                  id="edit-reason-name"
                  value={editReasonForm.data.name}
                  onChange={(e) =>
                    editReasonForm.setData('name', e.target.value)
                  }
                  required
                />
                <InputError message={editReasonForm.errors.name} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="edit-reason-desc">Descripción</Label>
                <Input
                  id="edit-reason-desc"
                  value={editReasonForm.data.description}
                  onChange={(e) =>
                    editReasonForm.setData('description', e.target.value)
                  }
                />
                <InputError message={editReasonForm.errors.description} />
              </div>
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setEditingReason(null)}
              >
                Cancelar
              </Button>
              <Button
                type="submit"
                disabled={editReasonForm.processing}
                className="bg-primary text-primary-foreground hover:bg-primary/90"
              >
                Guardar Cambios
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* DIALOG: Delete Adjustment Reason Confirmation */}
      <Dialog
        open={deletingReason !== null}
        onOpenChange={(open) => !open && setDeletingReason(null)}
      >
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>¿Eliminar motivo de ajuste?</DialogTitle>
            <DialogDescription>
              Esta acción dará de baja el motivo "
              <span className="font-semibold text-foreground">
                {deletingReason?.name}
              </span>
              ". No se podrá eliminar si ya ha sido utilizado en ajustes o
              movimientos registrados.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter className="gap-2 sm:gap-0">
            <Button
              type="button"
              variant="outline"
              onClick={() => setDeletingReason(null)}
            >
              Cancelar
            </Button>
            <Button
              type="button"
              variant="destructive"
              onClick={handleDeleteReasonConfirm}
            >
              Confirmar Eliminación
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* DIALOG: Create Movement Type */}
      <Dialog open={isCreateTypeOpen} onOpenChange={setIsCreateTypeOpen}>
        <DialogContent className="sm:max-w-md">
          <form onSubmit={handleCreateTypeSubmit}>
            <DialogHeader>
              <DialogTitle>Nuevo Tipo de Movimiento de Stock</DialogTitle>
              <DialogDescription>
                Creá un tipo personalizado para tipar los movimientos de
                mercadería.
              </DialogDescription>
            </DialogHeader>

            <div className="grid gap-4 py-4">
              <div className="grid gap-2">
                <Label htmlFor="create-type-name">Nombre *</Label>
                <Input
                  id="create-type-name"
                  placeholder="Ej. Devolución de mercadería dañada"
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
