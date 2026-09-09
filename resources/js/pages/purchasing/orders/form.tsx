import { Head, Link, useForm } from '@inertiajs/react';
import {
  ArrowLeft,
  CheckCircle2,
  Loader2,
  Package,
  Plus,
  Save,
  Search,
  Trash2,
  X,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { searchArticles } from '@/actions/App/Http/Controllers/Purchasing/PurchaseOrderController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index, show, store, update } from '@/routes/purchasing/orders';
import type { BreadcrumbItem } from '@/types';

type OrderData = App.Data.Purchasing.PurchaseOrderData;
type SupplierOption = App.Data.Purchasing.SupplierOptionData;
type WarehouseOption = App.Data.Purchasing.PurchaseOrderWarehouseOptionData;
type ArticleOption = App.Data.Purchasing.PurchaseOrderArticleOptionData;

type Props = {
  order: OrderData | null;
  suppliers: SupplierOption[];
  warehouses: WarehouseOption[];
  articles: ArticleOption[];
  today: string;
};

type ItemFormRow = {
  article_id: number;
  quantity: string;
  unit_price: string;
};

export default function PurchaseOrderForm({
  order,
  suppliers = [],
  warehouses = [],
  articles = [],
  today,
}: Props) {
  const isEditing = Boolean(order);

  // New item selector state
  const [selectedArticleId, setSelectedArticleId] = useState<string>('');
  const [selectedArticleObject, setSelectedArticleObject] =
    useState<ArticleOption | null>(null);
  const [articleSearchTerm, setArticleSearchTerm] = useState<string>('');
  const [isArticleSearchOpen, setIsArticleSearchOpen] =
    useState<boolean>(false);
  const [searchResults, setSearchResults] = useState<ArticleOption[]>(articles);
  const [isSearching, setIsSearching] = useState<boolean>(false);
  const searchContainerRef = useRef<HTMLDivElement>(null);
  const articleSearchInputRef = useRef<HTMLInputElement>(null);
  const quantityInputRef = useRef<HTMLInputElement>(null);

  const [itemQuantity, setItemQuantity] = useState<string>('1');
  const [itemPrice, setItemPrice] = useState<string>('');

  const sanitizeDecimal = (val: string) => {
    let clean = val.replace(/[^0-9.,]/g, '').replace(',', '.');
    const parts = clean.split('.');

    if (parts.length > 2) {
      clean = parts[0] + '.' + parts.slice(1).join('');
    }

    return clean;
  };

  useEffect(() => {
    const handleOutsideClick = (e: MouseEvent) => {
      if (
        searchContainerRef.current &&
        !searchContainerRef.current.contains(e.target as Node)
      ) {
        setIsArticleSearchOpen(false);
      }
    };

    document.addEventListener('mousedown', handleOutsideClick);

    return () => {
      document.removeEventListener('mousedown', handleOutsideClick);
    };
  }, []);

  const initialItems: ItemFormRow[] = useMemo(() => {
    if (!order?.items) {
      return [];
    }

    return order.items.map((i) => ({
      article_id: i.article_id,
      quantity: String(i.quantity),
      unit_price: String(i.unit_price),
    }));
  }, [order]);

  const { data, setData, post, put, processing, errors, transform } = useForm({
    supplier_id: order ? String(order.supplier_id) : '',
    warehouse_id: order ? String(order.warehouse_id) : '',
    payment_terms: order?.payment_terms ?? '',
    issue_date: order?.issue_date ?? today,
    expected_delivery_date: order?.expected_delivery_date ?? '',
    notes: order?.notes ?? '',
    status: 'borrador',
    items: initialItems,
  });

  const [knownArticlesMap, setKnownArticlesMap] = useState<
    Map<number, ArticleOption>
  >(() => {
    const map = new Map<number, ArticleOption>();

    articles.forEach((a) => map.set(a.id, a));

    if (order?.items) {
      order.items.forEach((item) => {
        if (!map.has(item.article_id)) {
          map.set(item.article_id, {
            id: item.article_id,
            internal_code: item.article_internal_code,
            description: item.article_description,
            unit_of_measure: item.unit_of_measure,
          });
        }
      });
    }

    return map;
  });

  const handleArticleSearchChange = (val: string) => {
    setArticleSearchTerm(val);
    setIsArticleSearchOpen(true);

    if (!val.trim()) {
      setSearchResults(articles);
      setIsSearching(false);
    } else {
      setIsSearching(true);
    }
  };

  const handleClearArticleSearch = () => {
    setArticleSearchTerm('');
    setSearchResults(articles);
    setIsSearching(false);
    articleSearchInputRef.current?.focus();
  };

  useEffect(() => {
    const term = articleSearchTerm.trim();

    if (!term) {
      return;
    }

    const timer = setTimeout(async () => {
      setIsSearching(true);

      try {
        const response = await fetch(
          searchArticles.url({ query: { search: term } }),
        );

        if (response.ok) {
          const results: ArticleOption[] = await response.json();

          setSearchResults(results);
          setKnownArticlesMap((prev) => {
            const next = new Map(prev);

            results.forEach((r) => next.set(r.id, r));

            return next;
          });
        }
      } catch (err) {
        console.error('Error searching articles', err);
      } finally {
        setIsSearching(false);
      }
    }, 300);

    return () => clearTimeout(timer);
  }, [articleSearchTerm]);

  const selectedArticle = useMemo(() => {
    if (!selectedArticleId) {
      return null;
    }

    const id = parseInt(selectedArticleId, 10);

    return selectedArticleObject ?? knownArticlesMap.get(id) ?? null;
  }, [selectedArticleId, selectedArticleObject, knownArticlesMap]);

  const totalCalculated = useMemo(() => {
    let sum = 0;

    for (const item of data.items) {
      const q = parseFloat(item.quantity) || 0;
      const p = parseFloat(item.unit_price) || 0;
      sum += q * p;
    }

    return Math.round(sum * 100) / 100;
  }, [data.items]);

  const handleSelectArticle = (art: ArticleOption) => {
    setSelectedArticleId(String(art.id));
    setSelectedArticleObject(art);
    setArticleSearchTerm('');
    setSearchResults(articles);
    setIsSearching(false);
    setIsArticleSearchOpen(false);
    setTimeout(() => {
      quantityInputRef.current?.focus();
    }, 50);
  };

  const handleClearSelectedArticle = () => {
    setSelectedArticleId('');
    setSelectedArticleObject(null);
    setArticleSearchTerm('');
    setSearchResults(articles);
    setIsSearching(false);
    setTimeout(() => {
      articleSearchInputRef.current?.focus();
    }, 50);
  };

  const handleAddItem = () => {
    if (!selectedArticleId) {
      toast.error('Seleccioná un artículo para agregar');

      return;
    }

    const artId = parseInt(selectedArticleId, 10);

    if (data.items.some((i) => i.article_id === artId)) {
      toast.error('Este artículo ya fue agregado a la orden');

      return;
    }

    const q = parseFloat(itemQuantity);

    if (isNaN(q) || q <= 0) {
      toast.error('La cantidad debe ser mayor a cero');

      return;
    }

    const p = parseFloat(itemPrice);

    if (isNaN(p) || p <= 0) {
      toast.error('El precio unitario debe ser mayor a cero');

      return;
    }

    setData('items', [
      ...data.items,
      {
        article_id: artId,
        quantity: itemQuantity,
        unit_price: itemPrice,
      },
    ]);

    setSelectedArticleId('');
    setSelectedArticleObject(null);
    setArticleSearchTerm('');
    setItemQuantity('1');
    setItemPrice('');
    setTimeout(() => {
      articleSearchInputRef.current?.focus();
    }, 50);
  };

  const handleRemoveItem = (indexToRemove: number) => {
    setData(
      'items',
      data.items.filter((_, idx) => idx !== indexToRemove),
    );
  };

  const handleItemChange = (
    index: number,
    field: 'quantity' | 'unit_price',
    value: string,
  ) => {
    const updated = [...data.items];
    const target = updated[index];

    if (target) {
      target[field] = value;
      setData('items', updated);
    }
  };

  const handleSubmit = (targetStatus: 'borrador' | 'emitida') => {
    if (!data.supplier_id) {
      toast.error('Seleccioná un proveedor');

      return;
    }

    if (!data.warehouse_id) {
      toast.error('Seleccioná un depósito de destino');

      return;
    }

    if (targetStatus === 'emitida' && data.items.length === 0) {
      toast.error(
        'No podés emitir una orden sin al menos un artículo en el detalle',
      );

      return;
    }

    transform((formData) => ({
      ...formData,
      status: targetStatus,
    }));

    if (isEditing && order) {
      put(update.url({ purchase_order: order.id }), {
        onSuccess: () => {
          toast.success(
            targetStatus === 'emitida'
              ? 'Orden de compra emitida correctamente'
              : 'Borrador actualizado correctamente',
          );
        },
        onError: () => {
          toast.error('Revisá los errores en el formulario');
        },
      });
    } else {
      post(store.url(), {
        onSuccess: () => {
          toast.success(
            targetStatus === 'emitida'
              ? 'Orden de compra emitida correctamente'
              : 'Orden guardada como borrador',
          );
        },
        onError: () => {
          toast.error('Revisá los errores en el formulario');
        },
      });
    }
  };

  return (
    <>
      <Head
        title={
          isEditing
            ? `Editar orden ${order?.order_number}`
            : 'Nueva orden de compra'
        }
      />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div className="space-y-1">
            <Button variant="ghost" size="sm" asChild className="mb-2 gap-1">
              <Link
                href={
                  isEditing && order
                    ? show.url({ purchase_order: order.id })
                    : index.url()
                }
              >
                <ArrowLeft className="size-4" />
                Volver
              </Link>
            </Button>
            <Heading
              title={
                isEditing
                  ? `Editar orden de compra ${order?.order_number}`
                  : 'Nueva orden de compra'
              }
              description="Ingresá los datos del proveedor, depósito destino y renglones de artículos pactados."
            />
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <Button
              type="button"
              variant="outline"
              disabled={processing}
              onClick={() => handleSubmit('borrador')}
              className="gap-2"
            >
              <Save className="size-4" />
              Guardar borrador
            </Button>
            <Button
              type="button"
              disabled={processing}
              onClick={() => handleSubmit('emitida')}
              className="gap-2 bg-emerald-600 text-white hover:bg-emerald-700"
            >
              <CheckCircle2 className="size-4" />
              Emitir orden
            </Button>
          </div>
        </div>

        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
          {/* Cabecera */}
          <Card className="border bg-card shadow-xs lg:col-span-3">
            <CardHeader className="pb-3">
              <CardTitle className="text-base font-semibold">
                Datos de la orden de compra
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                <div className="min-w-0 space-y-1.5">
                  <Label htmlFor="supplier_id">
                    Proveedor <span className="text-destructive">*</span>
                  </Label>
                  <Select
                    value={data.supplier_id}
                    onValueChange={(val) => setData('supplier_id', val)}
                  >
                    <SelectTrigger id="supplier_id">
                      <SelectValue placeholder="Seleccionar proveedor..." />
                    </SelectTrigger>
                    <SelectContent>
                      {suppliers.map((s) => (
                        <SelectItem key={s.id} value={String(s.id)}>
                          {s.business_name} ({s.tax_id})
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <InputError message={errors.supplier_id} />
                </div>

                <div className="min-w-0 space-y-1.5">
                  <Label htmlFor="warehouse_id">
                    Depósito destino <span className="text-destructive">*</span>
                  </Label>
                  <Select
                    value={data.warehouse_id}
                    onValueChange={(val) => setData('warehouse_id', val)}
                  >
                    <SelectTrigger id="warehouse_id">
                      <SelectValue placeholder="Seleccionar depósito..." />
                    </SelectTrigger>
                    <SelectContent>
                      {warehouses.map((w) => (
                        <SelectItem key={w.id} value={String(w.id)}>
                          {w.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <InputError message={errors.warehouse_id} />
                </div>

                <div className="min-w-0 space-y-1.5">
                  <Label htmlFor="issue_date">
                    Fecha de emisión <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    id="issue_date"
                    type="date"
                    value={data.issue_date}
                    onChange={(e) => setData('issue_date', e.target.value)}
                  />
                  <InputError message={errors.issue_date} />
                </div>

                <div className="min-w-0 space-y-1.5">
                  <Label htmlFor="expected_delivery_date">
                    Fecha esperada de entrega
                  </Label>
                  <Input
                    id="expected_delivery_date"
                    type="date"
                    value={data.expected_delivery_date}
                    min={data.issue_date}
                    onChange={(e) =>
                      setData('expected_delivery_date', e.target.value)
                    }
                  />
                  <InputError message={errors.expected_delivery_date} />
                </div>

                <div className="min-w-0 space-y-1.5 sm:col-span-2">
                  <Label htmlFor="payment_terms">Condición de pago</Label>
                  <Input
                    id="payment_terms"
                    placeholder="Ej. Cuenta corriente 30 días, contado contra entrega..."
                    value={data.payment_terms}
                    onChange={(e) => setData('payment_terms', e.target.value)}
                  />
                  <InputError message={errors.payment_terms} />
                </div>

                <div className="min-w-0 space-y-1.5 sm:col-span-2">
                  <Label htmlFor="notes">Observaciones / Instrucciones</Label>
                  <Textarea
                    id="notes"
                    placeholder="Indicaciones para el proveedor, horarios de descarga..."
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                    rows={2}
                  />
                  <InputError message={errors.notes} />
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Selector de artículos y detalle */}
          <Card className="border bg-card shadow-xs lg:col-span-3">
            <CardHeader className="border-b pb-3">
              <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <CardTitle className="text-base font-semibold">
                    Renglones de la orden
                  </CardTitle>
                  <p className="mt-0.5 text-xs text-muted-foreground">
                    Agregá los artículos solicitados. Un artículo solo puede
                    figurar una vez en la orden.
                  </p>
                </div>
                <div className="text-right">
                  <span className="text-xs text-muted-foreground">
                    Total estimado:
                  </span>
                  <div className="text-xl font-bold text-primary">
                    {formatCurrency(totalCalculated)}
                  </div>
                </div>
              </div>
            </CardHeader>

            <CardContent className="space-y-4 pt-4">
              {/* Agregar artículo */}
              <div className="rounded-md border bg-muted/30 p-3">
                <div className="grid grid-cols-1 items-end gap-3 sm:grid-cols-12">
                  <div
                    ref={searchContainerRef}
                    className="relative space-y-1 sm:col-span-5"
                  >
                    <Label htmlFor="search_article">
                      Artículo del catálogo (escribí para buscar)
                    </Label>
                    {selectedArticle ? (
                      <div className="flex h-9 items-center justify-between rounded-md border bg-background px-3 py-1 text-sm shadow-xs">
                        <div className="flex items-center gap-2 overflow-hidden">
                          <span className="shrink-0 rounded bg-primary/10 px-1.5 py-0.5 font-mono text-xs font-semibold text-primary">
                            {selectedArticle.internal_code}
                          </span>
                          <span className="truncate font-medium">
                            {selectedArticle.description}
                          </span>
                          <span className="shrink-0 text-xs text-muted-foreground">
                            ({selectedArticle.unit_of_measure})
                          </span>
                        </div>
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          onClick={handleClearSelectedArticle}
                          className="h-7 shrink-0 px-2 text-xs text-muted-foreground hover:text-foreground"
                          title="Cambiar artículo"
                        >
                          <X className="mr-1 size-3.5" />
                          Cambiar
                        </Button>
                      </div>
                    ) : (
                      <div className="relative">
                        {isSearching ? (
                          <Loader2 className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />
                        ) : (
                          <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        )}
                        <Input
                          id="search_article"
                          ref={articleSearchInputRef}
                          value={articleSearchTerm}
                          onChange={(e) =>
                            handleArticleSearchChange(e.target.value)
                          }
                          onFocus={() => setIsArticleSearchOpen(true)}
                          placeholder="Escribí código interno o nombre..."
                          className="pr-8 pl-9"
                        />
                        {articleSearchTerm && (
                          <button
                            type="button"
                            onClick={handleClearArticleSearch}
                            className="absolute top-1/2 right-2.5 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                          >
                            <X className="size-4" />
                          </button>
                        )}

                        {isArticleSearchOpen && (
                          <div className="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border bg-popover p-1 shadow-lg">
                            {isSearching ? (
                              <div className="flex items-center justify-center gap-2 p-3 text-xs text-muted-foreground">
                                <Loader2 className="size-3.5 animate-spin" />
                                Buscando artículos...
                              </div>
                            ) : searchResults.length === 0 ? (
                              <div className="p-3 text-center text-xs text-muted-foreground">
                                No se encontraron artículos que coincidan con
                                &quot;{articleSearchTerm}&quot;.
                              </div>
                            ) : (
                              searchResults.map((art) => {
                                const isAdded = data.items.some(
                                  (it) => it.article_id === art.id,
                                );

                                return (
                                  <div
                                    key={art.id}
                                    onClick={() => {
                                      if (!isAdded) {
                                        handleSelectArticle(art);
                                      }
                                    }}
                                    className={`flex cursor-pointer items-center justify-between rounded-sm px-2.5 py-1.5 text-sm hover:bg-accent ${
                                      isAdded
                                        ? 'cursor-not-allowed opacity-50'
                                        : ''
                                    }`}
                                  >
                                    <div className="flex items-center gap-2 overflow-hidden">
                                      <span className="shrink-0 rounded bg-muted px-1.5 py-0.5 font-mono text-xs font-semibold">
                                        {art.internal_code}
                                      </span>
                                      <span className="truncate">
                                        {art.description}
                                      </span>
                                      <span className="shrink-0 text-xs text-muted-foreground">
                                        ({art.unit_of_measure})
                                      </span>
                                    </div>
                                    {isAdded && (
                                      <span className="shrink-0 text-xs text-muted-foreground">
                                        Ya agregado
                                      </span>
                                    )}
                                  </div>
                                );
                              })
                            )}
                          </div>
                        )}
                      </div>
                    )}
                  </div>

                  <div className="space-y-1 sm:col-span-2">
                    <Label htmlFor="add_qty">Cantidad</Label>
                    <Input
                      id="add_qty"
                      ref={quantityInputRef}
                      type="text"
                      inputMode="decimal"
                      placeholder="1"
                      value={itemQuantity}
                      onChange={(e) =>
                        setItemQuantity(sanitizeDecimal(e.target.value))
                      }
                      className="[appearance:textfield] font-mono [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                    />
                  </div>

                  <div className="space-y-1 sm:col-span-3">
                    <Label htmlFor="add_price">Precio unit. pactado ($)</Label>
                    <Input
                      id="add_price"
                      type="text"
                      inputMode="decimal"
                      placeholder="0.00"
                      value={itemPrice}
                      onChange={(e) =>
                        setItemPrice(sanitizeDecimal(e.target.value))
                      }
                      onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                          e.preventDefault();
                          handleAddItem();
                        }
                      }}
                      className="[appearance:textfield] font-mono [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                    />
                  </div>

                  <div className="sm:col-span-2">
                    <Button
                      type="button"
                      onClick={handleAddItem}
                      className="w-full gap-1"
                    >
                      <Plus className="size-4" />
                      Agregar
                    </Button>
                  </div>
                </div>
              </div>

              <InputError message={errors.items} />

              {/* Lista de renglones cargados */}
              <div className="rounded-md border">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-12">#</TableHead>
                      <TableHead>Código</TableHead>
                      <TableHead>Descripción</TableHead>
                      <TableHead className="w-24 text-center">U.M.</TableHead>
                      <TableHead className="w-32 text-right">
                        Cantidad
                      </TableHead>
                      <TableHead className="w-36 text-right">
                        Precio unit.
                      </TableHead>
                      <TableHead className="w-36 text-right">
                        Subtotal
                      </TableHead>
                      <TableHead className="w-16 text-center"></TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {data.items.length === 0 ? (
                      <TableRow>
                        <TableCell
                          colSpan={8}
                          className="py-8 text-center text-muted-foreground"
                        >
                          <Package className="mx-auto mb-1 size-6 opacity-40" />
                          No se agregaron artículos a la orden aún.
                        </TableCell>
                      </TableRow>
                    ) : (
                      data.items.map((item, index) => {
                        const article = knownArticlesMap.get(item.article_id);
                        const qty = parseFloat(item.quantity) || 0;
                        const price = parseFloat(item.unit_price) || 0;
                        const subtotal = Math.round(qty * price * 100) / 100;

                        return (
                          <TableRow key={item.article_id}>
                            <TableCell className="text-xs text-muted-foreground">
                              {index + 1}
                            </TableCell>
                            <TableCell className="font-mono text-xs font-semibold">
                              {article?.internal_code ?? '-'}
                            </TableCell>
                            <TableCell className="text-sm font-medium">
                              {article?.description ??
                                `Artículo #${item.article_id}`}
                            </TableCell>
                            <TableCell className="text-center text-xs text-muted-foreground">
                              {article?.unit_of_measure ?? 'u'}
                            </TableCell>
                            <TableCell className="text-right">
                              <Input
                                type="text"
                                inputMode="decimal"
                                value={item.quantity}
                                onChange={(e) =>
                                  handleItemChange(
                                    index,
                                    'quantity',
                                    sanitizeDecimal(e.target.value),
                                  )
                                }
                                className="h-8 [appearance:textfield] text-right font-mono [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                              />
                            </TableCell>
                            <TableCell className="text-right">
                              <Input
                                type="text"
                                inputMode="decimal"
                                value={item.unit_price}
                                onChange={(e) =>
                                  handleItemChange(
                                    index,
                                    'unit_price',
                                    sanitizeDecimal(e.target.value),
                                  )
                                }
                                className="h-8 [appearance:textfield] text-right font-mono [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                              />
                            </TableCell>
                            <TableCell className="text-right font-mono font-semibold">
                              {formatCurrency(subtotal)}
                            </TableCell>
                            <TableCell className="text-center">
                              <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => handleRemoveItem(index)}
                                className="size-8 text-destructive hover:bg-destructive/10"
                                title="Quitar renglón"
                              >
                                <Trash2 className="size-4" />
                              </Button>
                            </TableCell>
                          </TableRow>
                        );
                      })
                    )}
                  </TableBody>
                </Table>
              </div>

              {/* Pie con totales y aviso sin impuestos */}
              <div className="flex flex-col rounded-lg border bg-muted/40 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="text-xs text-muted-foreground">
                  * El total es la suma de los subtotales de artículos. No se
                  calculan impuestos discriminados.
                </div>
                <div className="mt-2 flex items-center gap-3 sm:mt-0">
                  <span className="text-sm font-semibold text-muted-foreground uppercase">
                    Total de la orden:
                  </span>
                  <span className="font-mono text-2xl font-bold text-primary">
                    {formatCurrency(totalCalculated)}
                  </span>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </>
  );
}

PurchaseOrderForm.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Compras', href: '#' },
    { title: 'Órdenes de compra', href: index() },
    { title: 'Formulario', href: '#' },
  ] satisfies BreadcrumbItem[],
};
