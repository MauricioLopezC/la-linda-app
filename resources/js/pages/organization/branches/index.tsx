import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Power, Search } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
  store,
  toggleStatus,
  update,
} from '@/actions/App/Http/Controllers/Organization/BranchController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import TablePagination from '@/components/table-pagination';
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
import type { BreadcrumbItem } from '@/types';

type Branch = App.Data.Organization.BranchData;

type Props = {
  branches: Branch[];
};

export default function BranchesIndex({ branches = [] }: Props) {
  const [searchTerm, setSearchTerm] = useState('');
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [editingBranch, setEditingBranch] = useState<Branch | null>(null);
  const [currentPage, setCurrentPage] = useState(1);
  const PAGE_SIZE = 10;

  const createForm = useForm({
    name: '',
    address: '',
    phone: '',
    is_active: true,
  });

  const editForm = useForm({
    name: '',
    address: '',
    phone: '',
    is_active: true,
  });

  const normalizedSearch = searchTerm.trim().toLowerCase();
  const filteredBranches = branches.filter(
    (branch) =>
      branch.name.toLowerCase().includes(normalizedSearch) ||
      (branch.address &&
        branch.address.toLowerCase().includes(normalizedSearch)) ||
      (branch.phone && branch.phone.toLowerCase().includes(normalizedSearch)),
  );

  const totalPages = Math.max(
    1,
    Math.ceil(filteredBranches.length / PAGE_SIZE),
  );
  const safeCurrentPage = Math.min(currentPage, totalPages);
  const paginatedBranches = filteredBranches.slice(
    (safeCurrentPage - 1) * PAGE_SIZE,
    safeCurrentPage * PAGE_SIZE,
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
        toast.success('Sucursal creada correctamente');
      },
      onError: () => {
        toast.error('Hubo un error al crear la sucursal');
      },
    });
  };

  const handleOpenEdit = (branch: Branch) => {
    setEditingBranch(branch);
    editForm.setData({
      name: branch.name,
      address: branch.address ?? '',
      phone: branch.phone ?? '',
      is_active: branch.is_active,
    });
    editForm.clearErrors();
  };

  const handleEditSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!editingBranch) {
      return;
    }

    editForm.put(update.url({ branch: editingBranch.id }), {
      preserveScroll: true,
      onSuccess: () => {
        setEditingBranch(null);
        toast.success('Sucursal actualizada correctamente');
      },
      onError: () => {
        toast.error('Error al actualizar la sucursal');
      },
    });
  };

  const handleToggleStatus = (branch: Branch) => {
    router.patch(
      toggleStatus.url({ branch: branch.id }),
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(`Estado de "${branch.name}" actualizado`);
        },
        onError: (errors) => {
          toast.error(
            errors.branch ||
              'No se pudo dar de baja la sucursal porque tiene existencias o movimientos registrados',
          );
        },
      },
    );
  };

  return (
    <>
      <Head title="Sucursales" />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <Heading
            title="Sucursales"
            description="Administrá las sucursales de la empresa."
          />
        </div>

        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative max-w-sm flex-1">
            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder="Buscar sucursal por nombre..."
              value={searchTerm}
              onChange={(e) => {
                setSearchTerm(e.target.value);
                setCurrentPage(1);
              }}
              className="pl-9"
            />
          </div>

          <Button onClick={handleOpenCreate}>
            <Plus className="mr-1.5 size-4" />
            Nueva Sucursal
          </Button>
        </div>

        <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nombre</TableHead>
                <TableHead>Dirección</TableHead>
                <TableHead>Teléfono</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredBranches.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={5}
                    className="py-12 text-center text-muted-foreground"
                  >
                    No se encontraron sucursales registradas.
                  </TableCell>
                </TableRow>
              ) : (
                paginatedBranches.map((branch) => (
                  <TableRow key={branch.id}>
                    <TableCell className="font-medium">{branch.name}</TableCell>
                    <TableCell>{branch.address || '—'}</TableCell>
                    <TableCell>{branch.phone || '—'}</TableCell>
                    <TableCell>
                      <Badge
                        variant={branch.is_active ? 'default' : 'secondary'}
                      >
                        {branch.is_active ? 'Activa' : 'Inactiva'}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => handleOpenEdit(branch)}
                      >
                        <Pencil className="size-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => handleToggleStatus(branch)}
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
        <TablePagination
          currentPage={safeCurrentPage}
          totalPages={totalPages}
          totalItems={filteredBranches.length}
          pageSize={PAGE_SIZE}
          onPageChange={setCurrentPage}
          entityName="sucursales"
        />
      </div>

      {/* DIALOG: Create Branch */}
      <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
        <DialogContent className="sm:max-w-md">
          <form onSubmit={handleCreateSubmit}>
            <DialogHeader>
              <DialogTitle>Nueva Sucursal</DialogTitle>
            </DialogHeader>
            <div className="grid gap-4 py-4">
              <div className="grid gap-2">
                <Label htmlFor="create-branch-name">Nombre *</Label>
                <Input
                  id="create-branch-name"
                  value={createForm.data.name}
                  onChange={(e) => createForm.setData('name', e.target.value)}
                  required
                />
                <InputError message={createForm.errors.name} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="create-branch-address">Dirección</Label>
                <Input
                  id="create-branch-address"
                  value={createForm.data.address}
                  onChange={(e) =>
                    createForm.setData('address', e.target.value)
                  }
                />
                <InputError message={createForm.errors.address} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="create-branch-phone">Teléfono</Label>
                <Input
                  id="create-branch-phone"
                  value={createForm.data.phone}
                  onChange={(e) => createForm.setData('phone', e.target.value)}
                />
                <InputError message={createForm.errors.phone} />
              </div>

              <div className="flex items-center gap-2">
                <Checkbox
                  id="create-branch-active"
                  checked={createForm.data.is_active}
                  onCheckedChange={(checked) =>
                    createForm.setData('is_active', checked === true)
                  }
                />
                <Label htmlFor="create-branch-active">Sucursal activa</Label>
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
                Crear Sucursal
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* DIALOG: Edit Branch */}
      <Dialog
        open={editingBranch !== null}
        onOpenChange={(open) => !open && setEditingBranch(null)}
      >
        <DialogContent className="sm:max-w-md">
          <form onSubmit={handleEditSubmit}>
            <DialogHeader>
              <DialogTitle>Editar Sucursal</DialogTitle>
            </DialogHeader>
            <div className="grid gap-4 py-4">
              <div className="grid gap-2">
                <Label htmlFor="edit-branch-name">Nombre *</Label>
                <Input
                  id="edit-branch-name"
                  value={editForm.data.name}
                  onChange={(e) => editForm.setData('name', e.target.value)}
                  required
                />
                <InputError message={editForm.errors.name} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="edit-branch-address">Dirección</Label>
                <Input
                  id="edit-branch-address"
                  value={editForm.data.address}
                  onChange={(e) => editForm.setData('address', e.target.value)}
                />
                <InputError message={editForm.errors.address} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="edit-branch-phone">Teléfono</Label>
                <Input
                  id="edit-branch-phone"
                  value={editForm.data.phone}
                  onChange={(e) => editForm.setData('phone', e.target.value)}
                />
                <InputError message={editForm.errors.phone} />
              </div>

              <div className="flex items-center gap-2">
                <Checkbox
                  id="edit-branch-active"
                  checked={editForm.data.is_active}
                  onCheckedChange={(checked) =>
                    editForm.setData('is_active', checked === true)
                  }
                />
                <Label htmlFor="edit-branch-active">Sucursal activa</Label>
              </div>
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setEditingBranch(null)}
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

BranchesIndex.layout = {
  breadcrumbs: [
    {
      title: 'Dashboard',
      href: '/dashboard',
    },
    {
      title: 'Sucursales',
      href: '/organization/branches',
    },
  ] satisfies BreadcrumbItem[],
};
