import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Power, Search } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
  store,
  toggleStatus,
  update,
} from '@/actions/App/Http/Controllers/Catalog/BrandController';
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
import { index } from '@/routes/catalog/brands';
import type { BreadcrumbItem } from '@/types';

type Brand = App.Data.Catalog.BrandData;
type Props = { brands: Brand[] };
type BrandFormData = { name: string; is_active: boolean };

export default function BrandsIndex({ brands = [] }: Props) {
  const [searchTerm, setSearchTerm] = useState('');
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [editingBrand, setEditingBrand] = useState<Brand | null>(null);
  const createForm = useForm<BrandFormData>({ name: '', is_active: true });
  const editForm = useForm<BrandFormData>({ name: '', is_active: true });
  const filteredBrands = brands.filter((brand) =>
    brand.name.toLowerCase().includes(searchTerm.trim().toLowerCase()),
  );

  const openCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    setIsCreateOpen(true);
  };

  const openEdit = (brand: Brand) => {
    editForm.setData({ name: brand.name, is_active: brand.is_active });
    editForm.clearErrors();
    setEditingBrand(brand);
  };

  const submitCreate = (event: React.FormEvent) => {
    event.preventDefault();
    createForm.post(store.url(), {
      preserveScroll: true,
      onSuccess: () => {
        setIsCreateOpen(false);
        toast.success('Marca creada correctamente');
      },
      onError: () => toast.error('Revisá los datos de la marca'),
    });
  };

  const submitEdit = (event: React.FormEvent) => {
    event.preventDefault();

    if (!editingBrand) {
      return;
    }

    editForm.put(update.url({ brand: editingBrand.id }), {
      preserveScroll: true,
      onSuccess: () => {
        setEditingBrand(null);
        toast.success('Marca actualizada correctamente');
      },
      onError: () => toast.error('Revisá los datos de la marca'),
    });
  };

  const toggle = (brand: Brand) => {
    router.patch(
      toggleStatus.url({ brand: brand.id }),
      {},
      {
        preserveScroll: true,
        onSuccess: () => toast.success(`Estado de "${brand.name}" actualizado`),
        onError: (errors) =>
          toast.error(errors.brand ?? 'No se pudo actualizar la marca'),
      },
    );
  };

  const formFields = (form: typeof createForm, prefix: 'create' | 'edit') => (
    <div className="grid gap-4 py-4">
      <div className="grid gap-2">
        <Label htmlFor={`${prefix}-brand-name`}>Nombre *</Label>
        <Input
          id={`${prefix}-brand-name`}
          value={form.data.name}
          onChange={(event) => form.setData('name', event.target.value)}
          required
        />
        <InputError message={form.errors.name} />
      </div>
      <div className="flex items-center gap-2">
        <Checkbox
          id={`${prefix}-brand-active`}
          checked={form.data.is_active}
          onCheckedChange={(checked) =>
            form.setData('is_active', checked === true)
          }
        />
        <Label htmlFor={`${prefix}-brand-active`}>Marca activa</Label>
      </div>
      <InputError message={form.errors.is_active} />
    </div>
  );

  return (
    <>
      <Head title="Marcas" />
      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <Heading
          title="Marcas"
          description="Administrá las marcas disponibles para los artículos."
        />
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative max-w-sm flex-1">
            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder="Buscar marca..."
              value={searchTerm}
              onChange={(event) => setSearchTerm(event.target.value)}
              className="pl-9"
            />
          </div>
          <Button onClick={openCreate}>
            <Plus className="mr-1.5 size-4" />
            Nueva marca
          </Button>
        </div>
        <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nombre</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredBrands.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={3}
                    className="py-12 text-center text-muted-foreground"
                  >
                    No se encontraron marcas registradas.
                  </TableCell>
                </TableRow>
              ) : (
                filteredBrands.map((brand) => (
                  <TableRow key={brand.id}>
                    <TableCell className="font-medium">{brand.name}</TableCell>
                    <TableCell>
                      <Badge
                        variant={brand.is_active ? 'default' : 'secondary'}
                      >
                        {brand.is_active ? 'Activa' : 'Inactiva'}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => openEdit(brand)}
                        aria-label={`Editar ${brand.name}`}
                      >
                        <Pencil className="size-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => toggle(brand)}
                        aria-label={`Cambiar estado de ${brand.name}`}
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
              <DialogTitle>Nueva marca</DialogTitle>
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
                Crear marca
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
      <Dialog
        open={editingBrand !== null}
        onOpenChange={(open) => !open && setEditingBrand(null)}
      >
        <DialogContent className="sm:max-w-md">
          <form onSubmit={submitEdit}>
            <DialogHeader>
              <DialogTitle>Editar marca</DialogTitle>
            </DialogHeader>
            {formFields(editForm, 'edit')}
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setEditingBrand(null)}
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

BrandsIndex.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Marcas', href: index() },
  ] satisfies BreadcrumbItem[],
};
