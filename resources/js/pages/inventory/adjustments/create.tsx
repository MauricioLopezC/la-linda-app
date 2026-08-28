import { Head, Link, useForm } from '@inertiajs/react';
import {
  AlertTriangle,
  ArrowLeft,
  Barcode,
  CheckCircle2,
  Loader2,
  Search,
  Trash2,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
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
import { formatStockQuantity } from '@/lib/utils';
import { articles, store } from '@/routes/inventory/adjustments';
import { index as stocksIndex } from '@/routes/inventory/stocks';
import type { BreadcrumbItem } from '@/types';

type WarehouseOption = App.Data.Inventory.WarehouseData;
type ReasonOption = App.Data.Inventory.StockAdjustmentReasonData;
type ArticleOption = App.Data.Inventory.ArticleStockOptionData;

interface AdjustmentItemDraft {
  article_id: number;
  internal_code: string;
  description: string;
  brand_name: string | null;
  category_name: string;
  unit_of_measure_name: string;
  unit_of_measure_abbreviation: string;
  system_quantity: number;
  counted_quantity: number | '';
}

interface Props {
  warehouses: WarehouseOption[];
  reasons: ReasonOption[];
  initialWarehouseId?: number | null;
}

/** Tope de conteo físico por artículo. Debe coincidir con StoreStockAdjustmentRequest. */
const MAX_COUNTED_QUANTITY = 100_000;

export default function CreateStockAdjustment({
  warehouses,
  reasons,
  initialWarehouseId,
}: Props) {
  const { data, setData, post, processing, errors, transform } = useForm({
    warehouse_id: initialWarehouseId ? String(initialWarehouseId) : '',
    stock_adjustment_reason_id: '',
    notes: '',
    items: [] as Array<{ article_id: number; counted_quantity: number }>,
  });

  const [itemsDraft, setItemsDraft] = useState<AdjustmentItemDraft[]>([]);
  const [searchTerm, setSearchTerm] = useState('');
  const [searchResults, setSearchResults] = useState<ArticleOption[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [isSearchOpen, setIsSearchOpen] = useState(false);
  const [isConfirmOpen, setIsConfirmOpen] = useState(false);
  const searchInputRef = useRef<HTMLInputElement>(null);

  // Transform form data on submit
  transform((formData) => ({
    ...formData,
    items: itemsDraft.map((item) => ({
      article_id: item.article_id,
      counted_quantity:
        item.counted_quantity === '' ? 0 : Number(item.counted_quantity),
    })),
  }));

  const handleSearchChange = (value: string) => {
    setSearchTerm(value);

    if (!value.trim()) {
      setSearchResults([]);
      setIsSearchOpen(false);
    }
  };

  // Debounced search for articles
  useEffect(() => {
    const trimmed = searchTerm.trim();

    if (!data.warehouse_id || !trimmed) {
      return;
    }

    const timer = setTimeout(async () => {
      setIsSearching(true);

      try {
        const res = await fetch(
          articles.url({
            query: { warehouse_id: data.warehouse_id, search: trimmed },
          }),
        );

        if (res.ok) {
          const json = await res.json();
          setSearchResults(json);
          setIsSearchOpen(true);
        }
      } catch (err) {
        console.error('Error fetching articles:', err);
      } finally {
        setIsSearching(false);
      }
    }, 250);

    return () => clearTimeout(timer);
  }, [searchTerm, data.warehouse_id]);

  // Handle warehouse change
  const handleWarehouseChange = (val: string) => {
    if (itemsDraft.length > 0) {
      if (
        confirm(
          'Al cambiar el depósito se reiniciará la lista de artículos cargados. ¿Desea continuar?',
        )
      ) {
        setItemsDraft([]);
        setData('warehouse_id', val);
      }
    } else {
      setData('warehouse_id', val);
    }
  };

  // Add article to draft
  const handleAddArticle = useCallback((article: ArticleOption) => {
    setItemsDraft((prev) => {
      if (prev.some((it) => it.article_id === article.id)) {
        return prev;
      }

      const sysQty = Number(article.current_stock) || 0;

      return [
        ...prev,
        {
          article_id: article.id,
          internal_code: article.internal_code,
          description: article.description,
          brand_name: article.brand_name,
          category_name: article.category_name,
          unit_of_measure_name: article.unit_of_measure_name,
          unit_of_measure_abbreviation: article.unit_of_measure_abbreviation,
          system_quantity: sysQty,
          counted_quantity: sysQty,
        },
      ];
    });
    setSearchTerm('');
    setSearchResults([]);
    setIsSearchOpen(false);
    searchInputRef.current?.focus();
  }, []);

  // Update counted quantity
  const handleQuantityChange = (articleId: number, value: string) => {
    setItemsDraft((prev) =>
      prev.map((it) => {
        if (it.article_id !== articleId) {
          return it;
        }

        if (value === '') {
          return { ...it, counted_quantity: '' };
        }

        let num = Math.min(
          MAX_COUNTED_QUANTITY,
          Math.max(0, parseFloat(value) || 0),
        );

        if (
          !unitAllowsDecimals(
            it.unit_of_measure_name,
            it.unit_of_measure_abbreviation,
          )
        ) {
          num = Math.round(num);
        }

        return { ...it, counted_quantity: num };
      }),
    );
  };

  // Remove article from draft
  const handleRemoveArticle = (articleId: number) => {
    setItemsDraft((prev) => prev.filter((it) => it.article_id !== articleId));
  };

  // Difference summary
  const summary = useMemo(() => {
    let positive = 0;
    let negative = 0;
    let neutral = 0;

    for (const it of itemsDraft) {
      const counted =
        it.counted_quantity === '' ? 0 : Number(it.counted_quantity);
      const delta = counted - it.system_quantity;

      if (delta > 0.0001) {
        positive++;
      } else if (delta < -0.0001) {
        negative++;
      } else {
        neutral++;
      }
    }

    return { positive, negative, neutral, total: itemsDraft.length };
  }, [itemsDraft]);

  const selectedWarehouse = warehouses.find(
    (w) => String(w.id) === data.warehouse_id,
  );
  const selectedReason = reasons.find(
    (r) => String(r.id) === data.stock_adjustment_reason_id,
  );

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (
      !data.warehouse_id ||
      !data.stock_adjustment_reason_id ||
      itemsDraft.length === 0
    ) {
      return;
    }

    setIsConfirmOpen(true);
  };

  const handleConfirmSubmit = () => {
    setIsConfirmOpen(false);
    post(store.url());
  };

  return (
    <>
      <Head title="Registrar Ajuste de Stock" />

      <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
        {/* Header */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">
              Registrar Ajuste de Stock
            </h1>
            <p className="text-sm text-muted-foreground">
              Documentá recuentos físicos, diferencias de inventario, roturas o
              mermas con trazabilidad inmutable.
            </p>
          </div>
          <Button variant="outline" asChild className="gap-2">
            <Link href={stocksIndex()}>
              <ArrowLeft className="h-4 w-4" />
              Volver a Existencias
            </Link>
          </Button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Top Parameters Card */}
          <Card>
            <CardHeader className="pb-4">
              <CardTitle className="text-base font-semibold">
                1. Datos del Encabezado
              </CardTitle>
              <CardDescription>
                Indicá el depósito donde se realizó el control y la
                justificación obligatoria del ajuste.
              </CardDescription>
            </CardHeader>
            <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
              {/* Warehouse selector */}
              <div className="space-y-2">
                <Label htmlFor="warehouse_id">
                  Depósito físico <span className="text-destructive">*</span>
                </Label>
                <Select
                  value={data.warehouse_id}
                  onValueChange={handleWarehouseChange}
                >
                  <SelectTrigger id="warehouse_id" className="w-full">
                    <SelectValue placeholder="Seleccioná un depósito..." />
                  </SelectTrigger>
                  <SelectContent>
                    {warehouses.map((w) => (
                      <SelectItem key={w.id} value={String(w.id)}>
                        {w.name} ({w.branch_name})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {errors.warehouse_id && (
                  <p className="text-xs text-destructive">
                    {errors.warehouse_id}
                  </p>
                )}
              </div>

              {/* Adjustment Reason selector */}
              <div className="space-y-2">
                <Label htmlFor="stock_adjustment_reason_id">
                  Motivo de ajuste documentado{' '}
                  <span className="text-destructive">*</span>
                </Label>
                <Select
                  value={data.stock_adjustment_reason_id}
                  onValueChange={(val) =>
                    setData('stock_adjustment_reason_id', val)
                  }
                >
                  <SelectTrigger
                    id="stock_adjustment_reason_id"
                    className="w-full"
                  >
                    <SelectValue placeholder="Seleccioná el motivo..." />
                  </SelectTrigger>
                  <SelectContent>
                    {reasons.map((r) => (
                      <SelectItem key={r.id} value={String(r.id)}>
                        {r.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {errors.stock_adjustment_reason_id && (
                  <p className="text-xs text-destructive">
                    {errors.stock_adjustment_reason_id}
                  </p>
                )}
              </div>

              {/* Observations notes */}
              <div className="space-y-2 md:col-span-2">
                <Label htmlFor="notes">
                  Observaciones / Justificación adicional (opcional)
                </Label>
                <textarea
                  id="notes"
                  value={data.notes}
                  onChange={(e) => setData('notes', e.target.value)}
                  placeholder="Detalle o circunstancia del recuento (ej: Mercadería rota detectada durante descarga de camión)..."
                  rows={2}
                  maxLength={500}
                  className="flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                />
                {errors.notes && (
                  <p className="text-xs text-destructive">{errors.notes}</p>
                )}
              </div>
            </CardContent>
          </Card>

          {/* Article Live Search and Items Table */}
          <Card>
            <CardHeader className="pb-4">
              <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <CardTitle className="text-base font-semibold">
                    2. Detalle de Artículos a Ajustar
                  </CardTitle>
                  <CardDescription>
                    Buscá por nombre, código interno o escaneá con la pistola de
                    código de barras.
                  </CardDescription>
                </div>
                {itemsDraft.length > 0 && (
                  <div
                    className="flex items-center gap-2 text-xs"
                    title="Cantidad de artículos en cada estado, no unidades. El detalle por unidad se ve en la columna Diferencia."
                  >
                    <Badge
                      variant="outline"
                      className="border-emerald-500/30 bg-emerald-500/10 text-emerald-600"
                    >
                      {summary.positive} {pluralizeArticulos(summary.positive)}{' '}
                      con sobrante
                    </Badge>
                    <Badge
                      variant="outline"
                      className="border-rose-500/30 bg-rose-500/10 text-rose-600"
                    >
                      {summary.negative} {pluralizeArticulos(summary.negative)}{' '}
                      con faltante
                    </Badge>
                    <Badge
                      variant="outline"
                      className="bg-muted text-muted-foreground"
                    >
                      {summary.neutral} {pluralizeArticulos(summary.neutral)}{' '}
                      sin cambio
                    </Badge>
                  </div>
                )}
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              {/* Search Bar with Autocomplete */}
              <div className="relative">
                <div className="relative flex items-center">
                  <Search className="pointer-events-none absolute left-3 h-4 w-4 text-muted-foreground" />
                  <Input
                    ref={searchInputRef}
                    value={searchTerm}
                    onChange={(e) => handleSearchChange(e.target.value)}
                    onFocus={() => {
                      if (searchResults.length > 0) {
                        setIsSearchOpen(true);
                      }
                    }}
                    placeholder={
                      !data.warehouse_id
                        ? 'Primero seleccioná un depósito arriba para habilitar la búsqueda...'
                        : 'Escribí nombre, código interno (ART-...) o escaneá código de barras...'
                    }
                    disabled={!data.warehouse_id}
                    className="pr-10 pl-9"
                  />
                  {isSearching && (
                    <Loader2 className="absolute right-3 h-4 w-4 animate-spin text-muted-foreground" />
                  )}
                  {!isSearching && searchTerm && (
                    <Barcode className="absolute right-3 h-4 w-4 text-muted-foreground" />
                  )}
                </div>

                {/* Floating Autocomplete Dropdown */}
                {isSearchOpen && searchResults.length > 0 && (
                  <div className="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border bg-popover shadow-lg">
                    {searchResults.map((article) => {
                      const isAlreadyAdded = itemsDraft.some(
                        (it) => it.article_id === article.id,
                      );

                      return (
                        <div
                          key={article.id}
                          onClick={() => {
                            if (!isAlreadyAdded) {
                              handleAddArticle(article);
                            }
                          }}
                          className={`flex cursor-pointer items-center justify-between border-b p-3 last:border-0 hover:bg-accent ${
                            isAlreadyAdded
                              ? 'cursor-not-allowed opacity-50'
                              : ''
                          }`}
                        >
                          <div>
                            <div className="flex items-center gap-2">
                              <span className="rounded bg-muted px-1.5 py-0.5 font-mono text-xs font-semibold">
                                {article.internal_code}
                              </span>
                              <span className="text-sm font-medium">
                                {article.description}
                              </span>
                            </div>
                            <div className="mt-0.5 flex gap-2 text-xs text-muted-foreground">
                              <span>{article.category_name}</span>
                              {article.brand_name && (
                                <span>• {article.brand_name}</span>
                              )}
                              {article.barcode && (
                                <span>• EAN: {article.barcode}</span>
                              )}
                            </div>
                          </div>
                          <div className="text-right">
                            <Badge
                              variant="secondary"
                              className="font-mono text-xs"
                            >
                              Stock:{' '}
                              {formatStockQuantity(
                                Number(article.current_stock),
                                article.unit_of_measure_name,
                              )}{' '}
                              {article.unit_of_measure_abbreviation}
                            </Badge>
                            {isAlreadyAdded && (
                              <span className="mt-0.5 block text-[10px] text-muted-foreground">
                                Ya agregado
                              </span>
                            )}
                          </div>
                        </div>
                      );
                    })}
                  </div>
                )}
              </div>

              {errors.items && (
                <p className="text-xs text-destructive">{errors.items}</p>
              )}

              {/* Items Table */}
              {itemsDraft.length === 0 ? (
                <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed p-8 text-center text-muted-foreground">
                  <Barcode className="h-8 w-8 text-muted-foreground/50" />
                  <p className="text-sm font-medium">
                    No hay artículos agregados al ajuste
                  </p>
                  <p className="max-w-sm text-xs">
                    Utilizá el buscador superior para agregar los productos que
                    vas a ajustar en este depósito.
                  </p>
                </div>
              ) : (
                <div className="overflow-hidden rounded-md border">
                  <Table>
                    <TableHeader className="bg-muted/50">
                      <TableRow>
                        <TableHead className="w-[80px]">Código</TableHead>
                        <TableHead>Artículo</TableHead>
                        <TableHead className="w-[100px]">Unidad</TableHead>
                        <TableHead className="w-[140px] text-right">
                          Stock Sistema
                        </TableHead>
                        <TableHead className="w-[160px] text-right">
                          Conteo Físico
                        </TableHead>
                        <TableHead className="w-[150px] text-right">
                          Diferencia
                        </TableHead>
                        <TableHead className="w-[50px]"></TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {itemsDraft.map((item) => {
                        const counted =
                          item.counted_quantity === ''
                            ? 0
                            : Number(item.counted_quantity);
                        const delta = round3(counted - item.system_quantity);
                        const allowsDecimals = unitAllowsDecimals(
                          item.unit_of_measure_name,
                          item.unit_of_measure_abbreviation,
                        );

                        return (
                          <TableRow key={item.article_id}>
                            <TableCell className="font-mono text-xs font-semibold">
                              {item.internal_code}
                            </TableCell>
                            <TableCell>
                              <div className="text-sm font-medium">
                                {item.description}
                              </div>
                              <div className="text-xs text-muted-foreground">
                                {item.category_name}{' '}
                                {item.brand_name ? `• ${item.brand_name}` : ''}
                              </div>
                            </TableCell>
                            <TableCell className="text-xs text-muted-foreground">
                              {item.unit_of_measure_name}
                            </TableCell>
                            <TableCell className="text-right font-mono text-sm text-muted-foreground">
                              {formatStockQuantity(
                                item.system_quantity,
                                item.unit_of_measure_name,
                              )}
                            </TableCell>
                            <TableCell className="text-right">
                              <Input
                                type="number"
                                inputMode={
                                  allowsDecimals ? 'decimal' : 'numeric'
                                }
                                step={allowsDecimals ? '0.001' : '1'}
                                min="0"
                                max={MAX_COUNTED_QUANTITY}
                                onKeyDown={(e) => {
                                  if (!allowsDecimals && e.key === '.') {
                                    e.preventDefault();
                                  }
                                }}
                                value={item.counted_quantity}
                                onChange={(e) =>
                                  handleQuantityChange(
                                    item.article_id,
                                    e.target.value,
                                  )
                                }
                                className="ml-auto h-8 w-32 text-right font-mono text-sm"
                              />
                            </TableCell>
                            <TableCell className="text-right font-mono text-sm">
                              {delta > 0.0001 ? (
                                <span className="inline-flex items-center gap-1 rounded bg-emerald-500/10 px-2 py-0.5 text-xs font-semibold text-emerald-600">
                                  +
                                  {formatStockQuantity(
                                    delta,
                                    item.unit_of_measure_name,
                                  )}{' '}
                                  (Sobrante)
                                </span>
                              ) : delta < -0.0001 ? (
                                <span className="inline-flex items-center gap-1 rounded bg-rose-500/10 px-2 py-0.5 text-xs font-semibold text-rose-600">
                                  {formatStockQuantity(
                                    delta,
                                    item.unit_of_measure_name,
                                  )}{' '}
                                  (Faltante)
                                </span>
                              ) : (
                                <span className="rounded bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                  {formatStockQuantity(
                                    0,
                                    item.unit_of_measure_name,
                                  )}{' '}
                                  (Sin cambio)
                                </span>
                              )}
                            </TableCell>
                            <TableCell className="text-right">
                              <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() =>
                                  handleRemoveArticle(item.article_id)
                                }
                                className="h-8 w-8 text-destructive hover:bg-destructive/10 hover:text-destructive"
                              >
                                <Trash2 className="h-4 w-4" />
                              </Button>
                            </TableCell>
                          </TableRow>
                        );
                      })}
                    </TableBody>
                  </Table>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Bottom Actions */}
          <div className="flex flex-col items-center justify-between gap-4 pt-2 sm:flex-row">
            <div className="flex items-center gap-2 text-xs text-muted-foreground">
              <AlertTriangle className="h-4 w-4 shrink-0 text-amber-500" />
              <span>
                Al confirmar, el movimiento quedará registrado de forma
                inmutable y afectará las existencias inmediatamente.
              </span>
            </div>

            <div className="flex w-full items-center gap-3 sm:w-auto">
              <Button
                type="button"
                variant="outline"
                asChild
                className="w-full sm:w-auto"
              >
                <Link href={stocksIndex()}>Cancelar</Link>
              </Button>
              <Button
                type="submit"
                disabled={
                  processing ||
                  itemsDraft.length === 0 ||
                  !data.warehouse_id ||
                  !data.stock_adjustment_reason_id
                }
                className="w-full gap-2 sm:w-auto"
              >
                {processing ? (
                  <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                  <CheckCircle2 className="h-4 w-4" />
                )}
                Confirmar y Registrar Ajuste
              </Button>
            </div>
          </div>
        </form>

        {/* Confirmation Modal */}
        <Dialog open={isConfirmOpen} onOpenChange={setIsConfirmOpen}>
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>¿Confirmar registro de ajuste de stock?</DialogTitle>
              <DialogDescription asChild>
                <div className="space-y-3 pt-2 text-sm text-muted-foreground">
                  <p>
                    Estás a punto de confirmar el siguiente ajuste de
                    inventario:
                  </p>
                  <div className="space-y-1 rounded-md bg-muted p-3 text-xs text-foreground">
                    <div>
                      <span className="font-semibold">Depósito:</span>{' '}
                      {selectedWarehouse?.name} (
                      {selectedWarehouse?.branch_name})
                    </div>
                    <div>
                      <span className="font-semibold">Motivo:</span>{' '}
                      {selectedReason?.name}
                    </div>
                    <div>
                      <span className="font-semibold">
                        Artículos a ajustar:
                      </span>{' '}
                      {itemsDraft.length} ({summary.positive} con sobrante,{' '}
                      {summary.negative} con faltante)
                    </div>
                  </div>
                  <p className="text-xs font-medium text-amber-600 dark:text-amber-400">
                    ⚠️ Esta operación es definitiva e inmutable. No podrá ser
                    editada ni eliminada una vez registrada.
                  </p>
                </div>
              </DialogDescription>
            </DialogHeader>
            <DialogFooter className="gap-2 sm:gap-0">
              <Button
                type="button"
                variant="outline"
                onClick={() => setIsConfirmOpen(false)}
                disabled={processing}
              >
                Volver a revisar
              </Button>
              <Button
                type="button"
                onClick={handleConfirmSubmit}
                disabled={processing}
                className="gap-2"
              >
                {processing && <Loader2 className="h-4 w-4 animate-spin" />}
                Confirmar Ajuste
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </>
  );
}

CreateStockAdjustment.layout = {
  breadcrumbs: [
    {
      title: 'Inventario',
      href: '/inventory/stocks',
    },
    {
      title: 'Ajuste de Stock',
      href: '/inventory/adjustments/create',
    },
  ] satisfies BreadcrumbItem[],
};

function round3(n: number): number {
  return Math.round(n * 1000) / 1000;
}

function pluralizeArticulos(count: number): string {
  return count === 1 ? 'artículo' : 'artículos';
}

/**
 * Las unidades discretas (unidad/es, abreviatura "u") solo admiten conteos
 * enteros; el resto (kg, litros, etc.) admite decimales. Heurística por
 * nombre/abreviatura: no existe un flag en `units_of_measure`.
 */
function unitAllowsDecimals(name: string, abbreviation: string): boolean {
  const normalizedName = name.trim().toLowerCase();
  const normalizedAbbr = abbreviation.trim().toLowerCase();

  return (
    normalizedName !== 'unidad' &&
    normalizedName !== 'unidades' &&
    normalizedAbbr !== 'u'
  );
}
