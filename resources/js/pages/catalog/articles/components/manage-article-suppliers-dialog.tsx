import { router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Truck } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
  destroyForArticle,
  storeForArticle,
  updateForArticle,
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
  article: Article | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  availableSuppliers: Supplier[];
};

export default function ManageArticleSuppliersDialog({
  article,
  open,
  onOpenChange,
  availableSuppliers = [],
}: Props) {
  const [editingItem, setEditingItem] = useState<ArticleSupplier | null>(null);

  const attachForm = useForm({
    supplier_id: '',
    supplier_article_code: '',
    notes: '',
  });

  const editForm = useForm({
    supplier_article_code: '',
    notes: '',
  });

  if (!article) {
    return null;
  }

  const associatedSupplierIds = new Set(
    article.suppliers.map((item) => item.supplier_id),
  );

  const eligibleSuppliers = availableSuppliers.filter(
    (supplier) => !associatedSupplierIds.has(supplier.id),
  );

  const handleAttachSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    attachForm.post(storeForArticle.url({ article: article.id }), {
      preserveScroll: true,
      onSuccess: () => {
        attachForm.reset();
        attachForm.clearErrors();
        toast.success('Proveedor asociado correctamente');
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
      updateForArticle.url({
        article: article.id,
        supplier: editingItem.supplier_id,
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
        `¿Seguro que deseás desasociar al proveedor "${item.supplier_business_name}" de este artículo?`,
      )
    ) {
      return;
    }

    router.delete(
      destroyForArticle.url({
        article: article.id,
        supplier: item.supplier_id,
      }),
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(
            `Proveedor "${item.supplier_business_name}" desasociado`,
          );
        },
        onError: (errors) => {
          toast.error(
            errors.association ?? 'No se pudo desasociar el proveedor',
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
              <Truck className="size-5 text-primary" />
              <DialogTitle>Proveedores de: {article.description}</DialogTitle>
            </div>
            <DialogDescription>
              Código interno:{' '}
              <span className="font-semibold">{article.internal_code}</span> |
              Administrá los proveedores que abastecen este artículo y los
              códigos con los que lo identifican.
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-6 py-2">
            {/* Associated Suppliers List */}
            <div>
              <h4 className="mb-2 text-sm font-semibold">
                Proveedores asociados ({article.suppliers.length})
              </h4>
              <div className="rounded-md border">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Proveedor</TableHead>
                      <TableHead>Código en Proveedor</TableHead>
                      <TableHead>Último Costo</TableHead>
                      <TableHead>Observaciones</TableHead>
                      <TableHead className="text-right">Acciones</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {article.suppliers.length === 0 ? (
                      <TableRow>
                        <TableCell
                          colSpan={5}
                          className="py-8 text-center text-sm text-muted-foreground"
                        >
                          Este artículo no tiene proveedores asociados
                          actualmente.
                        </TableCell>
                      </TableRow>
                    ) : (
                      article.suppliers.map((item) => (
                        <TableRow key={item.id}>
                          <TableCell>
                            <div className="font-medium">
                              {item.supplier_business_name}
                            </div>
                            <div className="text-xs text-muted-foreground">
                              CUIT: {item.supplier_tax_id}
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
                                aria-label={`Editar código en proveedor ${item.supplier_business_name}`}
                              >
                                <Pencil className="size-4" />
                              </Button>
                              <Button
                                variant="ghost"
                                size="icon"
                                onClick={() => handleDetach(item)}
                                aria-label={`Desasociar ${item.supplier_business_name}`}
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
                Asociar nuevo proveedor
              </h4>

              {eligibleSuppliers.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                  Todos los proveedores activos disponibles ya se encuentran
                  asociados a este artículo.
                </p>
              ) : (
                <form
                  onSubmit={handleAttachSubmit}
                  className="grid gap-4 sm:grid-cols-12"
                >
                  <div className="sm:col-span-4">
                    <Label htmlFor="attach-supplier-id" className="text-xs">
                      Proveedor *
                    </Label>
                    <Select
                      value={attachForm.data.supplier_id}
                      onValueChange={(val) =>
                        attachForm.setData('supplier_id', val)
                      }
                    >
                      <SelectTrigger id="attach-supplier-id" className="mt-1">
                        <SelectValue placeholder="Seleccionar proveedor..." />
                      </SelectTrigger>
                      <SelectContent>
                        {eligibleSuppliers.map((sup) => (
                          <SelectItem key={sup.id} value={String(sup.id)}>
                            {sup.business_name} ({sup.tax_id})
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <InputError message={attachForm.errors.supplier_id} />
                  </div>

                  <div className="sm:col-span-3">
                    <Label htmlFor="attach-supplier-code" className="text-xs">
                      Código del proveedor *
                    </Label>
                    <Input
                      id="attach-supplier-code"
                      placeholder="Ej: ART-998"
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

                  <div className="sm:col-span-3">
                    <Label htmlFor="attach-notes" className="text-xs">
                      Observaciones
                    </Label>
                    <Input
                      id="attach-notes"
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
                        !attachForm.data.supplier_id ||
                        !attachForm.data.supplier_article_code
                      }
                    >
                      <Plus className="mr-1.5 size-4" />
                      Asociar proveedor
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
              <DialogTitle>Editar código de proveedor</DialogTitle>
              <DialogDescription>
                Proveedor:{' '}
                <span className="font-semibold">
                  {editingItem?.supplier_business_name}
                </span>
              </DialogDescription>
            </DialogHeader>

            <div className="grid gap-4 py-4">
              <div className="grid gap-2">
                <Label htmlFor="edit-supplier-code">
                  Código del artículo en el proveedor *
                </Label>
                <Input
                  id="edit-supplier-code"
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
                <Label htmlFor="edit-notes">Observaciones</Label>
                <Input
                  id="edit-notes"
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
