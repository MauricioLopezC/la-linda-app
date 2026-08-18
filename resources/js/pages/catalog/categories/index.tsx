import { Head, router, useForm } from '@inertiajs/react';
import {
  ChevronDown,
  FolderTree,
  Pencil,
  Plus,
  Power,
  Search,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
  store,
  toggleStatus,
  update,
} from '@/actions/App/Http/Controllers/Catalog/CategoryController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
import { dashboard } from '@/routes';
import { index } from '@/routes/catalog/categories';
import type { BreadcrumbItem } from '@/types';

type Category = App.Data.Catalog.CategoryData;
type Props = { categories: Category[] };
type CategoryFormData = { name: string; parent_id: string; is_active: boolean };

const rootValue = 'root';

export default function CategoriesIndex({ categories = [] }: Props) {
  const [searchTerm, setSearchTerm] = useState('');
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [editingCategory, setEditingCategory] = useState<Category | null>(null);
  const createForm = useForm<CategoryFormData>({
    name: '',
    parent_id: rootValue,
    is_active: true,
  });
  const editForm = useForm<CategoryFormData>({
    name: '',
    parent_id: rootValue,
    is_active: true,
  });
  const roots = categories.filter((category) => category.parent_id === null);
  const childrenByParent = categories.reduce<Record<number, Category[]>>(
    (grouped, category) => {
      if (category.parent_id !== null) {
        grouped[category.parent_id] = [
          ...(grouped[category.parent_id] ?? []),
          category,
        ];
      }

      return grouped;
    },
    {},
  );
  const normalizedSearch = searchTerm.trim().toLowerCase();
  const visibleRoots = roots.filter(
    (root) =>
      root.name.toLowerCase().includes(normalizedSearch) ||
      (childrenByParent[root.id] ?? []).some((child) =>
        child.name.toLowerCase().includes(normalizedSearch),
      ),
  );

  const openCreate = (parentId: number | null = null) => {
    createForm.reset();
    createForm.setData('parent_id', parentId?.toString() ?? rootValue);
    createForm.clearErrors();
    setIsCreateOpen(true);
  };

  const openEdit = (category: Category) => {
    editForm.setData({
      name: category.name,
      parent_id: category.parent_id?.toString() ?? rootValue,
      is_active: category.is_active,
    });
    editForm.clearErrors();
    setEditingCategory(category);
  };

  const submitCreate = (event: React.FormEvent) => {
    event.preventDefault();
    createForm.post(store.url(), {
      preserveScroll: true,
      onSuccess: () => {
        setIsCreateOpen(false);
        toast.success('Categoría creada correctamente');
      },
      onError: () => toast.error('Revisá los datos de la categoría'),
    });
  };

  const submitEdit = (event: React.FormEvent) => {
    event.preventDefault();

    if (!editingCategory) {
      return;
    }

    editForm.put(update.url({ category: editingCategory.id }), {
      preserveScroll: true,
      onSuccess: () => {
        setEditingCategory(null);
        toast.success('Categoría actualizada correctamente');
      },
      onError: () => toast.error('Revisá los datos de la categoría'),
    });
  };

  const toggle = (category: Category) => {
    router.patch(
      toggleStatus.url({ category: category.id }),
      {},
      {
        preserveScroll: true,
        onSuccess: () =>
          toast.success(`Estado de "${category.name}" actualizado`),
        onError: (errors) =>
          toast.error(errors.category ?? 'No se pudo actualizar la categoría'),
      },
    );
  };

  const categoryForm = (
    form: typeof createForm,
    prefix: 'create' | 'edit',
    category: Category | null = null,
  ) => {
    const hasChildren = category
      ? (childrenByParent[category.id]?.length ?? 0) > 0
      : false;

    return (
      <div className="grid gap-4 py-4">
        <div className="grid gap-2">
          <Label htmlFor={`${prefix}-category-name`}>Nombre *</Label>
          <Input
            id={`${prefix}-category-name`}
            value={form.data.name}
            onChange={(event) => form.setData('name', event.target.value)}
            required
          />
          <InputError message={form.errors.name} />
        </div>
        <div className="grid gap-2">
          <Label>Categoría padre</Label>
          <Select
            value={form.data.parent_id}
            onValueChange={(value) => form.setData('parent_id', value)}
            disabled={hasChildren}
          >
            <SelectTrigger className="w-full">
              <SelectValue placeholder="Sin categoría padre" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={rootValue}>Sin categoría padre</SelectItem>
              {roots
                .filter((root) => root.id !== category?.id)
                .map((root) => (
                  <SelectItem key={root.id} value={root.id.toString()}>
                    {root.name}
                  </SelectItem>
                ))}
            </SelectContent>
          </Select>
          {hasChildren && (
            <p className="text-xs text-muted-foreground">
              Una categoría con subcategorías debe permanecer en el nivel raíz.
            </p>
          )}
          <InputError message={form.errors.parent_id} />
        </div>
        <div className="flex items-center gap-2">
          <Checkbox
            id={`${prefix}-category-active`}
            checked={form.data.is_active}
            onCheckedChange={(checked) =>
              form.setData('is_active', checked === true)
            }
          />
          <Label htmlFor={`${prefix}-category-active`}>Categoría activa</Label>
        </div>
        <InputError message={form.errors.is_active} />
      </div>
    );
  };

  return (
    <>
      <Head title="Categorías" />
      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <Heading
          title="Categorías"
          description="Organizá el catálogo en categorías y subcategorías."
        />
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative max-w-sm flex-1">
            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder="Buscar categoría..."
              value={searchTerm}
              onChange={(event) => setSearchTerm(event.target.value)}
              className="pl-9"
            />
          </div>
          <Button onClick={() => openCreate()}>
            <Plus className="mr-1.5 size-4" />
            Nueva categoría
          </Button>
        </div>
        <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
          {visibleRoots.length === 0 ? (
            <div className="py-12 text-center text-muted-foreground">
              No se encontraron categorías registradas.
            </div>
          ) : (
            visibleRoots.map((root) => {
              const children = (childrenByParent[root.id] ?? []).filter(
                (child) =>
                  !normalizedSearch ||
                  root.name.toLowerCase().includes(normalizedSearch) ||
                  child.name.toLowerCase().includes(normalizedSearch),
              );

              return (
                <Collapsible key={root.id} defaultOpen>
                  <div className="flex items-center gap-3 border-b px-4 py-3 last:border-b-0">
                    <CollapsibleTrigger asChild>
                      <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Mostrar subcategorías de ${root.name}`}
                      >
                        <ChevronDown className="size-4" />
                      </Button>
                    </CollapsibleTrigger>
                    <FolderTree className="size-5 text-primary" />
                    <div className="min-w-0 flex-1">
                      <p className="font-medium">{root.name}</p>
                      <p className="text-xs text-muted-foreground">
                        {children.length}{' '}
                        {children.length === 1
                          ? 'subcategoría'
                          : 'subcategorías'}
                      </p>
                    </div>
                    <Badge variant={root.is_active ? 'default' : 'secondary'}>
                      {root.is_active ? 'Activa' : 'Inactiva'}
                    </Badge>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => openCreate(root.id)}
                      aria-label={`Agregar subcategoría a ${root.name}`}
                    >
                      <Plus className="size-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => openEdit(root)}
                      aria-label={`Editar ${root.name}`}
                    >
                      <Pencil className="size-4" />
                    </Button>
                    <Button
                      variant="ghost"
                      size="icon"
                      onClick={() => toggle(root)}
                      aria-label={`Cambiar estado de ${root.name}`}
                    >
                      <Power className="size-4" />
                    </Button>
                  </div>
                  <CollapsibleContent>
                    {children.map((child) => (
                      <div
                        key={child.id}
                        className="flex items-center gap-3 border-b bg-muted/20 py-3 pr-4 pl-16 last:border-b-0"
                      >
                        <div className="h-px w-5 bg-border" />
                        <div className="min-w-0 flex-1">
                          <p className="font-medium">{child.name}</p>
                          <p className="text-xs text-muted-foreground">
                            Subcategoría de {root.name}
                          </p>
                        </div>
                        <Badge
                          variant={child.is_active ? 'default' : 'secondary'}
                        >
                          {child.is_active ? 'Activa' : 'Inactiva'}
                        </Badge>
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={() => openEdit(child)}
                          aria-label={`Editar ${child.name}`}
                        >
                          <Pencil className="size-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={() => toggle(child)}
                          aria-label={`Cambiar estado de ${child.name}`}
                        >
                          <Power className="size-4" />
                        </Button>
                      </div>
                    ))}
                  </CollapsibleContent>
                </Collapsible>
              );
            })
          )}
        </div>
      </div>

      <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
        <DialogContent className="sm:max-w-md">
          <form onSubmit={submitCreate}>
            <DialogHeader>
              <DialogTitle>Nueva categoría</DialogTitle>
            </DialogHeader>
            {categoryForm(createForm, 'create')}
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setIsCreateOpen(false)}
              >
                Cancelar
              </Button>
              <Button type="submit" disabled={createForm.processing}>
                Crear categoría
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
      <Dialog
        open={editingCategory !== null}
        onOpenChange={(open) => !open && setEditingCategory(null)}
      >
        <DialogContent className="sm:max-w-md">
          <form onSubmit={submitEdit}>
            <DialogHeader>
              <DialogTitle>Editar categoría</DialogTitle>
            </DialogHeader>
            {categoryForm(editForm, 'edit', editingCategory)}
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setEditingCategory(null)}
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

CategoriesIndex.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Categorías', href: index() },
  ] satisfies BreadcrumbItem[],
};
