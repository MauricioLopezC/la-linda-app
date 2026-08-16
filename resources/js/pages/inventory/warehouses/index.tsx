import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Power, Search } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
  store,
  toggleStatus,
  update,
} from '@/actions/App/Http/Controllers/Inventory/WarehouseController';
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
import type { BreadcrumbItem } from '@/types';

type Warehouse = App.Data.Inventory.WarehouseData;
type Branch = App.Data.Organization.BranchData;

type Props = {
  warehouses: Warehouse[];
  branches: Branch[];
};

export default function WarehousesIndex({
  warehouses = [],
  branches = [],
}: Props) {
  const [searchTerm, setSearchTerm] = useState('');
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [editingWarehouse, setEditingWarehouse] = useState<Warehouse | null>(
    null,
  );

  const createForm = useForm({
    name: '',
    branch_id: '',
    is_online_channel: false,
    is_active: true,
  });

  const editForm = useForm({
    name: '',
    branch_id: '',
    is_online_channel: false,
    is_active: true,
  });

  const filteredWarehouses = warehouses.filter(
    (warehouse) =>
      warehouse.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      warehouse.branch_name.toLowerCase().includes(searchTerm.toLowerCase()),
  );

  const handleOpenCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    setIsCreateOpen(true);
  };

  const handleCreateSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    createForm.post(store.url(), {
      preserveScroll: true,
      onSuccess: () => {
        setIsCreateOpen(false);
        createForm.reset();
        toast.success('Depósito creado correctamente');
      },
      onError: () => {
        toast.error('Hubo un error al crear el depósito');
      },
    });
  };

  const handleOpenEdit = (warehouse: Warehouse) => {
    setEditingWarehouse(warehouse);
    editForm.setData({
      name: warehouse.name,
      branch_id: String(warehouse.branch_id),
      is_online_channel: warehouse.is_online_channel,
      is_active: warehouse.is_active,
    });
    editForm.clearErrors();
  };

  const handleEditSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!editingWarehouse) {
      return;
    }

    editForm.put(update.url({ warehouse: editingWarehouse.id }), {
      preserveScroll: true,
      onSuccess: () => {
        setEditingWarehouse(null);
        toast.success('Depósito actualizado correctamente');
      },
      onError: () => {
        toast.error('Error al actualizar el depósito');
      },
    });
  };

  const handleToggleStatus = (warehouse: Warehouse) => {
    router.patch(
      toggleStatus.url({ warehouse: warehouse.id }),
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(`Estado de "${warehouse.name}" actualizado`);
        },
        onError: (errors) => {
          toast.error(
            errors.warehouse ||
              'No se pudo dar de baja el depósito porque tiene existencias o movimientos registrados',
          );
        },
      },
    );
  };

  return (
    <>
      <Head title="Depósitos" />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <Heading
            title="Depósitos"
            description="Administrá los depósitos de cada sucursal y el canal online."
          />
        </div>

        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative max-w-sm flex-1">
            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder="Buscar depósito por nombre o sucursal..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="pl-9"
            />
          </div>

          <Button onClick={handleOpenCreate} disabled={branches.length === 0}>
            <Plus className="mr-1.5 size-4" />
            Nuevo Depósito
          </Button>
        </div>

        {branches.length === 0 && (
          <p className="text-sm text-muted-foreground">
            Primero registrá al menos una sucursal para poder crear depósitos.
          </p>
        )}

        <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nombre</TableHead>
                <TableHead>Sucursal</TableHead>
                <TableHead>Canal Online</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredWarehouses.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={5}
                    className="py-12 text-center text-muted-foreground"
                  >
                    No se encontraron depósitos registrados.
                  </TableCell>
                </TableRow>
              ) : (
                filteredWarehouses.map((warehouse) => (
                  <TableRow key={warehouse.id}>
                    <TableCell className="font-medium">
                      {warehouse.name}
                    </TableCell>
                    <TableCell>{warehouse.branch_name}</TableCell>
                    <TableCell>
                      {warehouse.is_online_channel ? (
                        <Badge>Canal Online</Badge>
                      ) : (
                        '—'
                      )}
                    </TableCell>
                    <TableCell>
                      <Badge
                        variant={warehouse.is_active ? 'default' : 'secondary'}
                      >
                        {warehouse.is_active ? 'Activo' : 'Inactivo'}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => handleOpenEdit(warehouse)}
                      >
                        <Pencil className="size-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => handleToggleStatus(warehouse)}
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

      {/* DIALOG: Create Warehouse */}
      <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
        <DialogContent className="sm:max-w-md">
          <form onSubmit={handleCreateSubmit}>
            <DialogHeader>
              <DialogTitle>Nuevo Depósito</DialogTitle>
            </DialogHeader>
            <div className="grid gap-4 py-4">
              <div className="grid gap-2">
                <Label htmlFor="create-warehouse-name">Nombre *</Label>
                <Input
                  id="create-warehouse-name"
                  value={createForm.data.name}
                  onChange={(e) => createForm.setData('name', e.target.value)}
                  required
                />
                <InputError message={createForm.errors.name} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="create-warehouse-branch">Sucursal *</Label>
                <Select
                  value={createForm.data.branch_id}
                  onValueChange={(val) => createForm.setData('branch_id', val)}
                >
                  <SelectTrigger id="create-warehouse-branch">
                    <SelectValue placeholder="Seleccioná una sucursal" />
                  </SelectTrigger>
                  <SelectContent>
                    {branches.map((branch) => (
                      <SelectItem key={branch.id} value={String(branch.id)}>
                        {branch.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <InputError message={createForm.errors.branch_id} />
              </div>

              <div className="flex items-center gap-2">
                <Checkbox
                  id="create-warehouse-online"
                  checked={createForm.data.is_online_channel}
                  onCheckedChange={(checked) =>
                    createForm.setData('is_online_channel', checked === true)
                  }
                />
                <Label htmlFor="create-warehouse-online">
                  Depósito asignado al canal online
                </Label>
              </div>
              <InputError message={createForm.errors.is_online_channel} />

              <div className="flex items-center gap-2">
                <Checkbox
                  id="create-warehouse-active"
                  checked={createForm.data.is_active}
                  onCheckedChange={(checked) =>
                    createForm.setData('is_active', checked === true)
                  }
                />
                <Label htmlFor="create-warehouse-active">Depósito activo</Label>
              </div>
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setIsCreateOpen(false)}
              >
                Cancelar
              </Button>
              <Button type="submit" disabled={createForm.processing}>
                Crear Depósito
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* DIALOG: Edit Warehouse */}
      <Dialog
        open={editingWarehouse !== null}
        onOpenChange={(open) => !open && setEditingWarehouse(null)}
      >
        <DialogContent className="sm:max-w-md">
          <form onSubmit={handleEditSubmit}>
            <DialogHeader>
              <DialogTitle>Editar Depósito</DialogTitle>
            </DialogHeader>
            <div className="grid gap-4 py-4">
              <div className="grid gap-2">
                <Label htmlFor="edit-warehouse-name">Nombre *</Label>
                <Input
                  id="edit-warehouse-name"
                  value={editForm.data.name}
                  onChange={(e) => editForm.setData('name', e.target.value)}
                  required
                />
                <InputError message={editForm.errors.name} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="edit-warehouse-branch">Sucursal *</Label>
                <Select
                  value={editForm.data.branch_id}
                  onValueChange={(val) => editForm.setData('branch_id', val)}
                >
                  <SelectTrigger id="edit-warehouse-branch">
                    <SelectValue placeholder="Seleccioná una sucursal" />
                  </SelectTrigger>
                  <SelectContent>
                    {branches.map((branch) => (
                      <SelectItem key={branch.id} value={String(branch.id)}>
                        {branch.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <InputError message={editForm.errors.branch_id} />
              </div>

              <div className="flex items-center gap-2">
                <Checkbox
                  id="edit-warehouse-online"
                  checked={editForm.data.is_online_channel}
                  onCheckedChange={(checked) =>
                    editForm.setData('is_online_channel', checked === true)
                  }
                />
                <Label htmlFor="edit-warehouse-online">
                  Depósito asignado al canal online
                </Label>
              </div>
              <InputError message={editForm.errors.is_online_channel} />

              <div className="flex items-center gap-2">
                <Checkbox
                  id="edit-warehouse-active"
                  checked={editForm.data.is_active}
                  onCheckedChange={(checked) =>
                    editForm.setData('is_active', checked === true)
                  }
                />
                <Label htmlFor="edit-warehouse-active">Depósito activo</Label>
              </div>
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setEditingWarehouse(null)}
              >
                Cancelar
              </Button>
              <Button type="submit" disabled={editForm.processing}>
                Guardar Cambios
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}

WarehousesIndex.layout = {
  breadcrumbs: [
    {
      title: 'Dashboard',
      href: '/dashboard',
    },
    {
      title: 'Depósitos',
      href: '/inventory/warehouses',
    },
  ] satisfies BreadcrumbItem[],
};
