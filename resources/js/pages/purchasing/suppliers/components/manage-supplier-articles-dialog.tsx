import { router, useForm } from '@inertiajs/react';
import { Package, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
  destroyForSupplier,
  storeForSupplier,
  updateForSupplier,
} from '@/actions/App/Http/Controllers/Catalog/ArticleSupplierController';
import InputError from '@/components/input-error';
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
import { formatCurrency } from '@/lib/utils';

type Article = App.Data.Catalog.ArticleData;
type ArticleSupplier = App.Data.Catalog.ArticleSupplierData;
type Supplier = App.Data.Purchasing.SupplierData;

type Props = {
  supplier: Supplier | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  availableArticles: Article[];
};

export default function ManageSupplierArticlesDialog({
  supplier,
  open,
  onOpenChange,
  availableArticles = [],
}: Props) {
  const [editingItem, setEditingItem] = useState<ArticleSupplier | null>(null);

  const attachForm = useForm({
    article_id: '',
    supplier_article_code: '',
    last_cost: '',
    notes: '',
  });

  const editForm = useForm({
    supplier_article_code: '',
    last_cost: '',
    notes: '',
  });

  if (!supplier) {
    return null;
  }

  const associatedArticleIds = new Set(
    supplier.articles.map((item) => item.article_id),
  );

  const eligibleArticles = availableArticles.filter(
    (article) => !associatedArticleIds.has(article.id),
  );

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
      last_cost: item.last_cost ?? '',
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
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
          <DialogHeader>
            <div className="flex items-center gap-2">
              <Package className="size-5 text-primary" />
              <DialogTitle>Artículos de: {supplier.business_name}</DialogTitle>
            </div>
            <DialogDescription>
              CUIT: <span className="font-semibold">{supplier.tax_id}</span> |
              Administrá los artículos que abastece este proveedor y los códigos
              con los que los identifica.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-6 py-2">
            {/* Associated Articles List */}
            <div>
              <h4 className="mb-2 text-sm font-semibold">
                Artículos asociados ({supplier.articles.length})
              </h4>
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
                    {supplier.articles.length === 0 ? (
                      <TableRow>
                        <TableCell
                          colSpan={5}
                          className="py-8 text-center text-sm text-muted-foreground"
                        >
                          Este proveedor no tiene artículos asociados
                          actualmente.
                        </TableCell>
                      </TableRow>
                    ) : (
                      supplier.articles.map((item) => (
                        <TableRow key={item.id}>
                          <TableCell>
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
                          <TableCell className="max-w-[200px] truncate text-xs text-muted-foreground">
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
                  className="grid gap-4 sm:grid-cols-12"
                >
                  <div className="sm:col-span-4">
                    <Label htmlFor="attach-article-id" className="text-xs">
                      Artículo *
                    </Label>
                    <Select
                      value={attachForm.data.article_id}
                      onValueChange={(val) =>
                        attachForm.setData('article_id', val)
                      }
                    >
                      <SelectTrigger id="attach-article-id" className="mt-1">
                        <SelectValue placeholder="Seleccionar artículo..." />
                      </SelectTrigger>
                      <SelectContent>
                        {eligibleArticles.map((art) => (
                          <SelectItem key={art.id} value={String(art.id)}>
                            {art.description} ({art.internal_code})
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <InputError message={attachForm.errors.article_id} />
                  </div>

                  <div className="sm:col-span-3">
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

                  <div className="sm:col-span-2">
                    <Label htmlFor="attach-art-cost" className="text-xs">
                      Último costo ($)
                    </Label>
                    <Input
                      id="attach-art-cost"
                      type="number"
                      step="0.01"
                      min="0.01"
                      placeholder="0.00"
                      className="mt-1"
                      value={attachForm.data.last_cost}
                      onChange={(e) =>
                        attachForm.setData('last_cost', e.target.value)
                      }
                    />
                    <InputError message={attachForm.errors.last_cost} />
                  </div>

                  <div className="sm:col-span-3">
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
          </div>

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
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
                <Label htmlFor="edit-art-last-cost">
                  Último costo de compra conocido ($)
                </Label>
                <Input
                  id="edit-art-last-cost"
                  type="number"
                  step="0.01"
                  min="0.01"
                  placeholder="0.00"
                  value={editForm.data.last_cost}
                  onChange={(e) =>
                    editForm.setData('last_cost', e.target.value)
                  }
                />
                <InputError message={editForm.errors.last_cost} />
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
