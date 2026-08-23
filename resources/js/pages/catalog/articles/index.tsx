import { Head, router, useForm } from '@inertiajs/react';
import { Ban, Pencil, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
  destroy,
  store,
  update,
} from '@/actions/App/Http/Controllers/Catalog/ArticleController';
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
import { dashboard } from '@/routes';
import { index } from '@/routes/catalog/articles';
import type { BreadcrumbItem } from '@/types';

type Article = App.Data.Catalog.ArticleData;
type Category = App.Data.Catalog.CategoryData;
type Brand = App.Data.Catalog.BrandData;
type UnitOfMeasure = App.Data.Catalog.UnitOfMeasureData;

type Props = {
  articles: Article[];
  categories: Category[];
  brands: Brand[];
  unitsOfMeasure: UnitOfMeasure[];
};

type ArticleFormData = {
  description: string;
  internal_code: string;
  barcode: string;
  category_id: string;
  subcategory_id: string;
  brand_id: string;
  unit_of_measure_id: string;
  status: string;
  is_online_publishable: boolean;
};

const NO_BRAND = 'none';
const NO_SUBCATEGORY = 'none';

const emptyForm: ArticleFormData = {
  description: '',
  internal_code: '',
  barcode: '',
  category_id: '',
  subcategory_id: '',
  brand_id: NO_BRAND,
  unit_of_measure_id: '',
  status: 'active',
  is_online_publishable: false,
};

const statusBadgeVariant = (status: string) => {
  if (status === 'active') {
    return 'default' as const;
  }

  if (status === 'inactive') {
    return 'secondary' as const;
  }

  return 'destructive' as const;
};

export default function ArticlesIndex({
  articles = [],
  categories = [],
  brands = [],
  unitsOfMeasure = [],
}: Props) {
  const [searchTerm, setSearchTerm] = useState('');
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [editingArticle, setEditingArticle] = useState<Article | null>(null);

  const createForm = useForm<ArticleFormData>(emptyForm);
  const editForm = useForm<ArticleFormData>(emptyForm);

  const rootCategories = categories.filter((category) => !category.parent_id);
  const subcategoriesOf = (rootId: string) =>
    categories.filter((category) => String(category.parent_id) === rootId);

  const categoryPath = (categoryId: number) => {
    const category = categories.find((c) => c.id === categoryId);

    if (!category) {
      return '—';
    }

    if (!category.parent_id) {
      return category.name;
    }

    const parent = categories.find((c) => c.id === category.parent_id);

    return parent ? `${parent.name} > ${category.name}` : category.name;
  };

  const filteredArticles = articles.filter((article) => {
    const term = searchTerm.trim().toLowerCase();

    return (
      article.description.toLowerCase().includes(term) ||
      article.internal_code.toLowerCase().includes(term) ||
      (article.barcode ?? '').toLowerCase().includes(term)
    );
  });

  const openCreate = () => {
    createForm.setData(emptyForm);
    createForm.clearErrors();
    setIsCreateOpen(true);
  };

  const openEdit = (article: Article) => {
    const category = categories.find((c) => c.id === article.category_id);
    const rootId = category?.parent_id
      ? String(category.parent_id)
      : String(article.category_id);
    const subcategoryId = category?.parent_id
      ? String(article.category_id)
      : NO_SUBCATEGORY;

    editForm.setData({
      description: article.description,
      internal_code: article.internal_code,
      barcode: article.barcode ?? '',
      category_id: rootId,
      subcategory_id: subcategoryId,
      brand_id: article.brand_id ? String(article.brand_id) : NO_BRAND,
      unit_of_measure_id: String(article.unit_of_measure_id),
      status: article.status,
      is_online_publishable: article.is_online_publishable,
    });
    editForm.clearErrors();
    setEditingArticle(article);
  };

  const resolveCategoryId = (data: ArticleFormData) =>
    data.subcategory_id && data.subcategory_id !== NO_SUBCATEGORY
      ? data.subcategory_id
      : data.category_id;

  const submitCreate = (event: React.FormEvent) => {
    event.preventDefault();

    createForm.transform((data) => ({
      ...data,
      category_id: resolveCategoryId(data),
      brand_id: data.brand_id === NO_BRAND ? '' : data.brand_id,
    }));

    createForm.post(store.url(), {
      preserveScroll: true,
      onSuccess: () => {
        setIsCreateOpen(false);
        toast.success('Artículo creado correctamente');
      },
      onError: () => toast.error('Revisá los datos del artículo'),
    });
  };

  const submitEdit = (event: React.FormEvent) => {
    event.preventDefault();

    if (!editingArticle) {
      return;
    }

    editForm.transform((data) => ({
      ...data,
      category_id: resolveCategoryId(data),
      brand_id: data.brand_id === NO_BRAND ? '' : data.brand_id,
    }));

    editForm.put(update.url({ article: editingArticle.id }), {
      preserveScroll: true,
      onSuccess: () => {
        setEditingArticle(null);
        toast.success('Artículo actualizado correctamente');
      },
      onError: () => toast.error('Revisá los datos del artículo'),
    });
  };

  const discontinue = (article: Article) => {
    router.delete(destroy.url({ article: article.id }), {
      preserveScroll: true,
      onSuccess: () =>
        toast.success(`"${article.description}" fue dado de baja`),
      onError: (errors) =>
        toast.error(errors.article ?? 'No se pudo dar de baja el artículo'),
    });
  };

  const formFields = (form: typeof createForm, prefix: 'create' | 'edit') => (
    <div className="grid gap-4 py-4">
      <div className="grid gap-2">
        <Label htmlFor={`${prefix}-article-description`}>Descripción *</Label>
        <Input
          id={`${prefix}-article-description`}
          value={form.data.description}
          onChange={(event) => form.setData('description', event.target.value)}
          required
        />
        <InputError message={form.errors.description} />
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="grid gap-2">
          <Label htmlFor={`${prefix}-article-internal-code`}>
            Código interno *
          </Label>
          <Input
            id={`${prefix}-article-internal-code`}
            value={form.data.internal_code}
            onChange={(event) =>
              form.setData('internal_code', event.target.value)
            }
            required
          />
          <InputError message={form.errors.internal_code} />
        </div>

        <div className="grid gap-2">
          <Label htmlFor={`${prefix}-article-barcode`}>Código de barras</Label>
          <Input
            id={`${prefix}-article-barcode`}
            value={form.data.barcode}
            onChange={(event) => form.setData('barcode', event.target.value)}
          />
          <InputError message={form.errors.barcode} />
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="grid gap-2">
          <Label htmlFor={`${prefix}-article-category`}>Categoría *</Label>
          <Select
            value={form.data.category_id}
            onValueChange={(val) =>
              form.setData((data) => ({
                ...data,
                category_id: val,
                subcategory_id: '',
              }))
            }
          >
            <SelectTrigger id={`${prefix}-article-category`}>
              <SelectValue placeholder="Seleccioná una categoría" />
            </SelectTrigger>
            <SelectContent>
              {rootCategories.map((category) => (
                <SelectItem key={category.id} value={String(category.id)}>
                  {category.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <InputError message={form.errors.category_id} />
        </div>

        <div className="grid gap-2">
          <Label htmlFor={`${prefix}-article-subcategory`}>Subcategoría</Label>
          <Select
            value={form.data.subcategory_id || NO_SUBCATEGORY}
            onValueChange={(val) =>
              form.setData('subcategory_id', val === NO_SUBCATEGORY ? '' : val)
            }
            disabled={subcategoriesOf(form.data.category_id).length === 0}
          >
            <SelectTrigger id={`${prefix}-article-subcategory`}>
              <SelectValue placeholder="Sin subcategoría" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={NO_SUBCATEGORY}>Sin subcategoría</SelectItem>
              {subcategoriesOf(form.data.category_id).map((subcategory) => (
                <SelectItem key={subcategory.id} value={String(subcategory.id)}>
                  {subcategory.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4">
        <div className="grid gap-2">
          <Label htmlFor={`${prefix}-article-brand`}>Marca</Label>
          <Select
            value={form.data.brand_id}
            onValueChange={(val) => form.setData('brand_id', val)}
          >
            <SelectTrigger id={`${prefix}-article-brand`}>
              <SelectValue placeholder="Seleccioná una marca" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={NO_BRAND}>Sin marca</SelectItem>
              {brands.map((brand) => (
                <SelectItem key={brand.id} value={String(brand.id)}>
                  {brand.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <InputError message={form.errors.brand_id} />
        </div>

        <div className="grid gap-2">
          <Label htmlFor={`${prefix}-article-unit`}>Unidad de medida *</Label>
          <Select
            value={form.data.unit_of_measure_id}
            onValueChange={(val) => form.setData('unit_of_measure_id', val)}
          >
            <SelectTrigger id={`${prefix}-article-unit`}>
              <SelectValue placeholder="Seleccioná una unidad" />
            </SelectTrigger>
            <SelectContent>
              {unitsOfMeasure.map((unit) => (
                <SelectItem key={unit.id} value={String(unit.id)}>
                  {unit.name} ({unit.abbreviation})
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <InputError message={form.errors.unit_of_measure_id} />
        </div>
      </div>

      <div className="grid gap-2">
        <Label htmlFor={`${prefix}-article-status`}>Estado *</Label>
        <Select
          value={form.data.status}
          onValueChange={(val) => form.setData('status', val)}
        >
          <SelectTrigger id={`${prefix}-article-status`}>
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="active">Activo</SelectItem>
            <SelectItem value="inactive">Inactivo</SelectItem>
            <SelectItem value="discontinued">Discontinuado</SelectItem>
          </SelectContent>
        </Select>
        <InputError message={form.errors.status} />
      </div>

      <div className="flex items-center gap-2">
        <Checkbox
          id={`${prefix}-article-online`}
          checked={form.data.is_online_publishable}
          onCheckedChange={(checked) =>
            form.setData('is_online_publishable', checked === true)
          }
        />
        <Label htmlFor={`${prefix}-article-online`}>
          Publicable en el canal online
        </Label>
      </div>
      <InputError message={form.errors.is_online_publishable} />
    </div>
  );

  return (
    <>
      <Head title="Artículos" />
      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <Heading
          title="Artículos"
          description="Administrá el catálogo único de artículos compartido por sucursales y canales."
        />
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative max-w-sm flex-1">
            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder="Buscar por descripción, código interno o de barras..."
              value={searchTerm}
              onChange={(event) => setSearchTerm(event.target.value)}
              className="pl-9"
            />
          </div>
          <Button onClick={openCreate}>
            <Plus className="mr-1.5 size-4" />
            Nuevo artículo
          </Button>
        </div>
        <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Código interno</TableHead>
                <TableHead>Descripción</TableHead>
                <TableHead>Categoría</TableHead>
                <TableHead>Marca</TableHead>
                <TableHead>Unidad</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredArticles.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={7}
                    className="py-12 text-center text-muted-foreground"
                  >
                    No se encontraron artículos registrados.
                  </TableCell>
                </TableRow>
              ) : (
                filteredArticles.map((article) => (
                  <TableRow key={article.id}>
                    <TableCell className="font-medium">
                      {article.internal_code}
                    </TableCell>
                    <TableCell>{article.description}</TableCell>
                    <TableCell>{categoryPath(article.category_id)}</TableCell>
                    <TableCell>{article.brand_name ?? '—'}</TableCell>
                    <TableCell>{article.unit_of_measure_name}</TableCell>
                    <TableCell>
                      <Badge variant={statusBadgeVariant(article.status)}>
                        {article.status_label}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => openEdit(article)}
                        aria-label={`Editar ${article.description}`}
                      >
                        <Pencil className="size-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => discontinue(article)}
                        disabled={article.status === 'discontinued'}
                        aria-label={`Dar de baja ${article.description}`}
                      >
                        <Ban className="size-4" />
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
        <DialogContent className="sm:max-w-lg">
          <form onSubmit={submitCreate}>
            <DialogHeader>
              <DialogTitle>Nuevo artículo</DialogTitle>
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
                Crear artículo
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
      <Dialog
        open={editingArticle !== null}
        onOpenChange={(open) => !open && setEditingArticle(null)}
      >
        <DialogContent className="sm:max-w-lg">
          <form onSubmit={submitEdit}>
            <DialogHeader>
              <DialogTitle>Editar artículo</DialogTitle>
            </DialogHeader>
            {formFields(editForm, 'edit')}
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setEditingArticle(null)}
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

ArticlesIndex.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Artículos', href: index() },
  ] satisfies BreadcrumbItem[],
};
