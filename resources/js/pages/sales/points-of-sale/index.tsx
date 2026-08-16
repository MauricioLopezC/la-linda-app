import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Power, Search } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
  store,
  toggleStatus,
  update,
} from '@/actions/App/Http/Controllers/Sales/PointOfSaleController';
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

type PointOfSale = App.Data.Sales.PointOfSaleData;
type Warehouse = App.Data.Inventory.WarehouseData;

type Props = {
  pointsOfSale: PointOfSale[];
  warehouses: Warehouse[];
};

export default function PointsOfSaleIndex({
  pointsOfSale = [],
  warehouses = [],
}: Props) {
  const [searchTerm, setSearchTerm] = useState('');
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [editingPointOfSale, setEditingPointOfSale] =
    useState<PointOfSale | null>(null);

  const createForm = useForm({
    number: '',
    warehouse_id: '',
    is_active: true,
  });

  const editForm = useForm({
    number: '',
    warehouse_id: '',
    is_active: true,
  });

  const filteredPointsOfSale = pointsOfSale.filter(
    (pos) =>
      String(pos.number).includes(searchTerm) ||
      pos.warehouse_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      pos.branch_name.toLowerCase().includes(searchTerm.toLowerCase()),
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
        toast.success('Punto de venta creado correctamente');
      },
      onError: () => {
        toast.error('Hubo un error al crear el punto de venta');
      },
    });
  };

  const handleOpenEdit = (pointOfSale: PointOfSale) => {
    setEditingPointOfSale(pointOfSale);
    editForm.setData({
      number: String(pointOfSale.number),
      warehouse_id: String(pointOfSale.warehouse_id),
      is_active: pointOfSale.is_active,
    });
    editForm.clearErrors();
  };

  const handleEditSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!editingPointOfSale) {
      return;
    }

    editForm.put(update.url({ point_of_sale: editingPointOfSale.id }), {
      preserveScroll: true,
      onSuccess: () => {
        setEditingPointOfSale(null);
        toast.success('Punto de venta actualizado correctamente');
      },
      onError: () => {
        toast.error('Error al actualizar el punto de venta');
      },
    });
  };

  const handleToggleStatus = (pointOfSale: PointOfSale) => {
    router.patch(
      toggleStatus.url({ point_of_sale: pointOfSale.id }),
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(`Estado del PDV N° ${pointOfSale.number} actualizado`);
        },
      },
    );
  };

  return (
    <>
      <Head title="Puntos de Venta" />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <Heading
            title="Puntos de Venta"
            description="Administrá los puntos de venta y el depósito del que descuentan stock."
          />
        </div>

        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative max-w-sm flex-1">
            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder="Buscar por número, depósito o sucursal..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="pl-9"
            />
          </div>

          <Button onClick={handleOpenCreate} disabled={warehouses.length === 0}>
            <Plus className="mr-1.5 size-4" />
            Nuevo Punto de Venta
          </Button>
        </div>

        {warehouses.length === 0 && (
          <p className="text-sm text-muted-foreground">
            Primero registrá al menos un depósito para poder crear puntos de
            venta.
          </p>
        )}

        <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Número</TableHead>
                <TableHead>Depósito</TableHead>
                <TableHead>Sucursal</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredPointsOfSale.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={5}
                    className="py-12 text-center text-muted-foreground"
                  >
                    No se encontraron puntos de venta registrados.
                  </TableCell>
                </TableRow>
              ) : (
                filteredPointsOfSale.map((pos) => (
                  <TableRow key={pos.id}>
                    <TableCell className="font-medium">{pos.number}</TableCell>
                    <TableCell>{pos.warehouse_name}</TableCell>
                    <TableCell>{pos.branch_name}</TableCell>
                    <TableCell>
                      <Badge variant={pos.is_active ? 'default' : 'secondary'}>
                        {pos.is_active ? 'Activo' : 'Inactivo'}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => handleOpenEdit(pos)}
                      >
                        <Pencil className="size-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => handleToggleStatus(pos)}
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

      {/* DIALOG: Create Point of Sale */}
      <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
        <DialogContent className="sm:max-w-md">
          <form onSubmit={handleCreateSubmit}>
            <DialogHeader>
              <DialogTitle>Nuevo Punto de Venta</DialogTitle>
            </DialogHeader>
            <div className="grid gap-4 py-4">
              <div className="grid gap-2">
                <Label htmlFor="create-pos-number">Número *</Label>
                <Input
                  id="create-pos-number"
                  type="number"
                  min={1}
                  value={createForm.data.number}
                  onChange={(e) => createForm.setData('number', e.target.value)}
                  required
                />
                <InputError message={createForm.errors.number} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="create-pos-warehouse">Depósito *</Label>
                <Select
                  value={createForm.data.warehouse_id}
                  onValueChange={(val) =>
                    createForm.setData('warehouse_id', val)
                  }
                >
                  <SelectTrigger id="create-pos-warehouse">
                    <SelectValue placeholder="Seleccioná un depósito" />
                  </SelectTrigger>
                  <SelectContent>
                    {warehouses.map((warehouse) => (
                      <SelectItem
                        key={warehouse.id}
                        value={String(warehouse.id)}
                      >
                        {warehouse.name} ({warehouse.branch_name})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <InputError message={createForm.errors.warehouse_id} />
              </div>

              <div className="flex items-center gap-2">
                <Checkbox
                  id="create-pos-active"
                  checked={createForm.data.is_active}
                  onCheckedChange={(checked) =>
                    createForm.setData('is_active', checked === true)
                  }
                />
                <Label htmlFor="create-pos-active">Punto de venta activo</Label>
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
                Crear Punto de Venta
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* DIALOG: Edit Point of Sale */}
      <Dialog
        open={editingPointOfSale !== null}
        onOpenChange={(open) => !open && setEditingPointOfSale(null)}
      >
        <DialogContent className="sm:max-w-md">
          <form onSubmit={handleEditSubmit}>
            <DialogHeader>
              <DialogTitle>Editar Punto de Venta</DialogTitle>
            </DialogHeader>
            <div className="grid gap-4 py-4">
              <div className="grid gap-2">
                <Label htmlFor="edit-pos-number">Número *</Label>
                <Input
                  id="edit-pos-number"
                  type="number"
                  min={1}
                  value={editForm.data.number}
                  onChange={(e) => editForm.setData('number', e.target.value)}
                  required
                />
                <InputError message={editForm.errors.number} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="edit-pos-warehouse">Depósito *</Label>
                <Select
                  value={editForm.data.warehouse_id}
                  onValueChange={(val) => editForm.setData('warehouse_id', val)}
                >
                  <SelectTrigger id="edit-pos-warehouse">
                    <SelectValue placeholder="Seleccioná un depósito" />
                  </SelectTrigger>
                  <SelectContent>
                    {warehouses.map((warehouse) => (
                      <SelectItem
                        key={warehouse.id}
                        value={String(warehouse.id)}
                      >
                        {warehouse.name} ({warehouse.branch_name})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <InputError message={editForm.errors.warehouse_id} />
              </div>

              <div className="flex items-center gap-2">
                <Checkbox
                  id="edit-pos-active"
                  checked={editForm.data.is_active}
                  onCheckedChange={(checked) =>
                    editForm.setData('is_active', checked === true)
                  }
                />
                <Label htmlFor="edit-pos-active">Punto de venta activo</Label>
              </div>
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setEditingPointOfSale(null)}
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

PointsOfSaleIndex.layout = {
  breadcrumbs: [
    {
      title: 'Dashboard',
      href: '/dashboard',
    },
    {
      title: 'Puntos de Venta',
      href: '/sales/points-of-sale',
    },
  ] satisfies BreadcrumbItem[],
};
