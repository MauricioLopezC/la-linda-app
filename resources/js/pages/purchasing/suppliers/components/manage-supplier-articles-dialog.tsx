import { router, useForm } from '@inertiajs/react';
import {
  Check,
  ChevronsUpDown,
  Package,
  Pencil,
  Plus,
  Search,
  Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import {
  destroyForSupplier,
  storeForSupplier,
  updateForSupplier,
} from '@/actions/App/Http/Controllers/Catalog/ArticleSupplierController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
  Command,
  CommandEmpty,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command';
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
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { cn, formatCurrency } from '@/lib/utils';

type Article = App.Data.Catalog.ArticleData;
type ArticleSupplier = App.Data.Catalog.ArticleSupplierData;
type Supplier = App.Data.Purchasing.SupplierData;

type Props = {
  supplier: Supplier | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  availableArticles: Article[];
};

/**
 * Lowercases and strips accents so "almibar" matches "Almíbar".
 */
function normalizeForSearch(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();
}

function matchesSearch(
  normalizedSearch: string,
  fields: Array<string | null | undefined>,
): boolean {
  if (normalizedSearch === '') {
    return true;
  }

  return fields.some(
    (field) => field && normalizeForSearch(field).includes(normalizedSearch),
  );
}

export default function ManageSupplierArticlesDialog({
  supplier,
  open,
  onOpenChange,
  availableArticles = [],
}: Props) {
  const [editingItem, setEditingItem] = useState<ArticleSupplier | null>(null);
  const [associatedSearch, setAssociatedSearch] = useState('');
  const [articlePickerOpen, setArticlePickerOpen] = useState(false);
  const [articlePickerSearch, setArticlePickerSearch] = useState('');

  const attachForm = useForm({
    article_id: '',
    supplier_article_code: '',
    notes: '',
  });

  const editForm = useForm({
    supplier_article_code: '',
    notes: '',
  });

  const associatedArticles = useMemo(
    () => supplier?.articles ?? [],
    [supplier],
  );

  const filteredAssociatedArticles = useMemo(() => {
    const normalizedSearch = normalizeForSearch(associatedSearch);

    return associatedArticles.filter((item) =>
      matchesSearch(normalizedSearch, [
        item.article_description,
        item.article_internal_code,
        item.article_barcode,
        item.supplier_article_code,
        item.notes,
      ]),
    );
  }, [associatedArticles, associatedSearch]);

  const eligibleArticles = useMemo(() => {
    const associatedArticleIds = new Set(
      associatedArticles.map((item) => item.article_id),
    );

    return availableArticles.filter(
      (article) => !associatedArticleIds.has(article.id),
    );
  }, [associatedArticles, availableArticles]);

  const filteredEligibleArticles = useMemo(() => {
    const normalizedSearch = normalizeForSearch(articlePickerSearch);

    return eligibleArticles.filter((article) =>
      matchesSearch(normalizedSearch, [
        article.description,
        article.internal_code,
        article.barcode,
      ]),
    );
  }, [eligibleArticles, articlePickerSearch]);

  if (!supplier) {
    return null;
  }

  const selectedArticle = eligibleArticles.find(
    (article) => String(article.id) === attachForm.data.article_id,
  );

  const handleOpenChange = (isOpen: boolean) => {
    if (!isOpen) {
      setAssociatedSearch('');
      setArticlePickerSearch('');
      attachForm.reset();
      attachForm.clearErrors();
    }

    onOpenChange(isOpen);
  };

  const handleSelectArticle = (article: Article) => {
    attachForm.setData('article_id', String(article.id));
    attachForm.clearErrors('article_id');
    setArticlePickerOpen(false);
    setArticlePickerSearch('');
  };

  const handleAttachSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    attachForm.post(storeForSupplier.url({ supplier: supplier.id }), {
      preserveScroll: true,
      onSuccess: () => {
        attachForm.reset();
        attachForm.clearErrors();
        toast.success('Artículo asociado correctamente');
      },
      onError: () => {
        toast.error('Revisá los datos de la asociación');
      },
    });
  };

  const handleOpenEdit = (item: ArticleSupplier) => {
    setEditingItem(item);
    editForm.setData({
      supplier_article_code: item.supplier_article_code,
      notes: item.notes ?? '',
    });
    editForm.clearErrors();
  };

  const handleEditSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!editingItem) {
      return;
    }

    editForm.put(
      updateForSupplier.url({
        supplier: supplier.id,
        article: editingItem.article_id,
      }),
      {
        preserveScroll: true,
        onSuccess: () => {
          setEditingItem(null);
          toast.success('Asociación actualizada correctamente');
        },
        onError: () => {
          toast.error('Revisá los datos ingresados');
        },
      },
    );
  };

  const handleDetach = (item: ArticleSupplier) => {
    if (
      !confirm(
        `¿Seguro que deseás desasociar el artículo "${item.article_description}" de este proveedor?`,
      )
    ) {
      return;
    }

    router.delete(
      destroyForSupplier.url({
        supplier: supplier.id,
        article: item.article_id,
      }),
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(`Artículo "${item.article_description}" desasociado`);
        },
        onError: (errors) => {
          toast.error(
            errors.association ?? 'No se pudo desasociar el artículo',
          );
        },
      },
    );
  };

  return (
    <>
      <Dialog open={open} onOpenChange={handleOpenChange}>
        <DialogContent className="flex max-h-[90vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-5xl">
          <DialogHeader className="border-b px-6 pt-6 pb-4">
            <div className="flex items-center gap-2 pr-6">
              <Package className="size-5 shrink-0 text-primary" />
              <DialogTitle>Artículos de: {supplier.business_name}</DialogTitle>
            </div>
            <DialogDescription>
              CUIT: <span className="font-semibold">{supplier.tax_id}</span> |
              Administrá los artículos que abastece este proveedor y los códigos
              con los que los identifica.
            </DialogDescription>
          </DialogHeader>

          <div className="min-h-0 flex-1 space-y-6 overflow-y-auto px-6 py-4">
            {/* Attach Form */}
            <div className="rounded-lg border bg-muted/30 p-4">
              <h4 className="mb-3 flex items-center gap-1.5 text-sm font-semibold">
                <Plus className="size-4 text-primary" />
                Asociar nuevo artículo
              </h4>

              {eligibleArticles.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                  Todos los artículos activos disponibles ya se encuentran
                  asociados a este proveedor.
                </p>
              ) : (
                <form
                  onSubmit={handleAttachSubmit}
                  className="grid gap-4 sm:grid-cols-12 sm:items-start"
                >
                  <div className="min-w-0 sm:col-span-5">
                    <Label htmlFor="attach-article-id" className="text-xs">
                      Artículo *
                    </Label>
                    <Popover
                      modal
                      open={articlePickerOpen}
                      onOpenChange={(isOpen) => {
                        setArticlePickerOpen(isOpen);

                        if (!isOpen) {
                          setArticlePickerSearch('');
                        }
                      }}
                    >
                      <PopoverTrigger asChild>
                        <Button
                          id="attach-article-id"
                          type="button"
                          variant="outline"
                          role="combobox"
                          aria-expanded={articlePickerOpen}
                          className="mt-1 w-full justify-between font-normal"
                        >
                          <span
                            className={cn(
                              'truncate',
                              !selectedArticle && 'text-muted-foreground',
                            )}
                          >
                            {selectedArticle
                              ? `${selectedArticle.description} (${selectedArticle.internal_code})`
                              : 'Buscar artículo...'}
                          </span>
                          <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                        </Button>
                      </PopoverTrigger>
                      <PopoverContent
                        className="w-[var(--radix-popover-trigger-width)] min-w-[20rem] p-0"
                        align="start"
                      >
                        <Command shouldFilter={false}>
                          <CommandInput
                            value={articlePickerSearch}
                            onValueChange={setArticlePickerSearch}
                            placeholder="Buscar por descripción, código o barras"
                          />
                          <CommandList>
                            <CommandEmpty>
                              No se encontraron artículos sin asociar.
                            </CommandEmpty>
                            {filteredEligibleArticles.map((article) => (
                              <CommandItem
                                key={article.id}
                                value={String(article.id)}
                                onSelect={() => handleSelectArticle(article)}
                              >
                                <Check
                                  className={cn(
                                    'size-4 shrink-0',
                                    attachForm.data.article_id ===
                                      String(article.id)
                                      ? 'opacity-100'
                                      : 'opacity-0',
                                  )}
                                />
                                <div className="flex min-w-0 flex-col gap-0.5">
                                  <span className="text-sm">
                                    {article.description}
                                  </span>
                                  <span className="text-xs text-muted-foreground">
                                    Cód. Int: {article.internal_code}
                                    {article.barcode
                                      ? ` | Barra: ${article.barcode}`
                                      : ''}
                                  </span>
                                </div>
                              </CommandItem>
                            ))}
                          </CommandList>
                        </Command>
                      </PopoverContent>
                    </Popover>
                    <InputError message={attachForm.errors.article_id} />
                  </div>

                  <div className="min-w-0 sm:col-span-3">
                    <Label
                      htmlFor="attach-supplier-art-code"
                      className="text-xs"
                    >
                      Código en este proveedor *
                    </Label>
                    <Input
                      id="attach-supplier-art-code"
                      placeholder="Ej: COD-1234"
                      className="mt-1 font-mono text-sm"
                      value={attachForm.data.supplier_article_code}
                      onChange={(e) =>
                        attachForm.setData(
                          'supplier_article_code',
                          e.target.value,
                        )
                      }
                      required
                    />
                    <InputError
                      message={attachForm.errors.supplier_article_code}
                    />
                  </div>

                  <div className="min-w-0 sm:col-span-4">
                    <Label htmlFor="attach-art-notes" className="text-xs">
                      Observaciones
                    </Label>
                    <Input
                      id="attach-art-notes"
                      placeholder="Opcional..."
                      className="mt-1"
                      value={attachForm.data.notes}
                      onChange={(e) =>
                        attachForm.setData('notes', e.target.value)
                      }
                    />
                    <InputError message={attachForm.errors.notes} />
                  </div>

                  <div className="flex justify-end sm:col-span-12">
                    <Button
                      type="submit"
                      disabled={
                        attachForm.processing ||
                        !attachForm.data.article_id ||
                        !attachForm.data.supplier_article_code
                      }
                    >
                      <Plus className="mr-1.5 size-4" />
                      Asociar artículo
                    </Button>
                  </div>
                </form>
              )}
            </div>

            {/* Associated Articles List */}
            <div className="min-w-0 space-y-3">
              <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <h4 className="text-sm font-semibold">
                  Artículos asociados (
                  {associatedSearch.trim() === ''
                    ? associatedArticles.length
                    : `${filteredAssociatedArticles.length} de ${associatedArticles.length}`}
                  )
                </h4>
                {associatedArticles.length > 0 && (
                  <div className="relative w-full sm:w-72">
                    <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                      type="search"
                      placeholder="Buscar artículo asociado..."
                      aria-label="Buscar artículo asociado"
                      className="pl-8"
                      value={associatedSearch}
                      onChange={(e) => setAssociatedSearch(e.target.value)}
                    />
                  </div>
                )}
              </div>

              <div className="rounded-md border">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Artículo</TableHead>
                      <TableHead>Código en Proveedor</TableHead>
                      <TableHead>Último Costo</TableHead>
                      <TableHead>Observaciones</TableHead>
                      <TableHead className="text-right">Acciones</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {filteredAssociatedArticles.length === 0 ? (
                      <TableRow>
                        <TableCell
                          colSpan={5}
                          className="py-8 text-center text-sm whitespace-normal text-muted-foreground"
                        >
                          {associatedArticles.length === 0
                            ? 'Este proveedor no tiene artículos asociados actualmente.'
                            : 'Ningún artículo asociado coincide con la búsqueda.'}
                        </TableCell>
                      </TableRow>
                    ) : (
                      filteredAssociatedArticles.map((item) => (
                        <TableRow key={item.id}>
                          <TableCell className="min-w-[16rem] whitespace-normal">
                            <div className="font-medium">
                              {item.article_description}
                            </div>
                            <div className="text-xs text-muted-foreground">
                              Cód. Int: {item.article_internal_code}
                              {item.article_barcode
                                ? ` | Barra: ${item.article_barcode}`
                                : ''}
                            </div>
                          </TableCell>
                          <TableCell className="font-mono text-sm font-medium">
                            {item.supplier_article_code}
                          </TableCell>
                          <TableCell>
                            {item.last_cost !== null
                              ? formatCurrency(item.last_cost)
                              : '—'}
                          </TableCell>
                          <TableCell className="max-w-[12rem] truncate text-xs text-muted-foreground">
                            {item.notes ?? '—'}
                          </TableCell>
                          <TableCell className="text-right">
                            <div className="flex items-center justify-end gap-1">
                              <Button
                                variant="ghost"
                                size="icon"
                                onClick={() => handleOpenEdit(item)}
                                aria-label={`Editar código en proveedor para ${item.article_description}`}
                              >
                                <Pencil className="size-4" />
                              </Button>
                              <Button
                                variant="ghost"
                                size="icon"
                                onClick={() => handleDetach(item)}
                                aria-label={`Desasociar ${item.article_description}`}
                                className="text-destructive hover:text-destructive"
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

          <DialogFooter className="border-t px-6 py-4">
            <Button
              type="button"
              variant="outline"
              onClick={() => handleOpenChange(false)}
            >
              Cerrar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* DIALOG: Edit Association */}
      <Dialog
        open={editingItem !== null}
        onOpenChange={(isOpen) => {
          if (!isOpen) {
            setEditingItem(null);
          }
        }}
      >
        <DialogContent className="sm:max-w-md">
          <form onSubmit={handleEditSubmit}>
            <DialogHeader>
              <DialogTitle>Editar código de artículo</DialogTitle>
              <DialogDescription>
                Artículo:{' '}
                <span className="font-semibold">
                  {editingItem?.article_description}
                </span>{' '}
                ({editingItem?.article_internal_code})
              </DialogDescription>
            </DialogHeader>

            <div className="grid gap-4 py-4">
              <div className="grid gap-2">
                <Label htmlFor="edit-supplier-art-code">
                  Código del artículo en el proveedor *
                </Label>
                <Input
                  id="edit-supplier-art-code"
                  className="font-mono text-sm"
                  value={editForm.data.supplier_article_code}
                  onChange={(e) =>
                    editForm.setData('supplier_article_code', e.target.value)
                  }
                  required
                />
                <InputError message={editForm.errors.supplier_article_code} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="edit-art-notes">Observaciones</Label>
                <Input
                  id="edit-art-notes"
                  placeholder="Opcional..."
                  value={editForm.data.notes}
                  onChange={(e) => editForm.setData('notes', e.target.value)}
                />
                <InputError message={editForm.errors.notes} />
              </div>
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setEditingItem(null)}
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
