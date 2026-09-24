import { Head, router } from '@inertiajs/react';
import {
  Ban,
  ChevronsUpDown,
  Loader2,
  ScanBarcode,
  Search,
  Trash2,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Command,
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
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { formatCurrency } from '@/lib/utils';
import { dashboard } from '@/routes';
import { discard, index, searchArticles } from '@/routes/sales/sales';
import {
  destroy as destroyItem,
  store as storeItem,
  update as updateItem,
} from '@/routes/sales/sales/items';
import type { BreadcrumbItem } from '@/types';

type Sale = App.Data.Sales.SaleData;
type SaleItem = App.Data.Sales.SaleItemData;
type ArticleOption = App.Data.Sales.SaleArticleOptionData;

type Props = {
  sale: Sale;
};

const saleStatusClasses: Record<string, string> = {
  abierta:
    'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300',
  descartada:
    'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300',
};

const priceOriginClasses: Record<string, string> = {
  particular:
    'border-violet-300 bg-violet-50 text-violet-800 dark:border-violet-800 dark:bg-violet-950/40 dark:text-violet-300',
  canal:
    'border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-300',
};

/**
 * Show the first validation error of a failed sale request as a toast.
 */
function toastFirstError(errors: Record<string, string>): void {
  const message = Object.values(errors)[0];

  toast.error(message ?? 'No se pudo completar la operación');
}

function formatQuantity(quantity: string, allowsDecimal: boolean): string {
  return Number(quantity).toLocaleString('es-AR', {
    minimumFractionDigits: 0,
    maximumFractionDigits: allowsDecimal ? 3 : 0,
  });
}

export default function SaleShow({ sale }: Props) {
  const [code, setCode] = useState('');
  const [codeError, setCodeError] = useState<string | undefined>();
  const [isAdding, setIsAdding] = useState(false);
  const [isDiscardDialogOpen, setIsDiscardDialogOpen] = useState(false);
  const codeInputRef = useRef<HTMLInputElement>(null);

  const focusCodeInput = () => {
    setTimeout(() => codeInputRef.current?.focus(), 50);
  };

  const addArticle = (payload: { code: string } | { article_id: number }) => {
    setIsAdding(true);
    router.post(storeItem.url(sale.id), payload, {
      preserveScroll: true,
      onSuccess: () => {
        setCode('');
        setCodeError(undefined);
      },
      onError: (errors) => {
        setCodeError(Object.values(errors)[0]);
        toastFirstError(errors);
      },
      onFinish: () => {
        setIsAdding(false);
        focusCodeInput();
      },
    });
  };

  const handleScanSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (code.trim() === '') {
      return;
    }

    addArticle({ code: code.trim() });
  };

  const handleDiscard = () => {
    router.post(
      discard.url(sale.id),
      {},
      {
        onSuccess: () => toast.success('Venta descartada'),
        onError: toastFirstError,
        onFinish: () => setIsDiscardDialogOpen(false),
      },
    );
  };

  return (
    <>
      <Head title={`Venta N° ${sale.id}`} />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
          <div className="flex items-center gap-3">
            <Heading
              title={`Venta N° ${sale.id}`}
              description={`${sale.branch_name} · PDV ${sale.point_of_sale_number} · ${sale.opened_at_formatted}`}
            />
            <Badge variant="outline" className={saleStatusClasses[sale.status]}>
              {sale.status_label}
            </Badge>
          </div>
          {sale.is_open && (
            <Button
              variant="outline"
              className="text-destructive"
              onClick={() => setIsDiscardDialogOpen(true)}
            >
              <Ban className="mr-1.5 size-4" />
              Descartar venta
            </Button>
          )}
        </div>

        <div className="grid gap-4 rounded-xl border border-sidebar-border bg-card p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
          <InfoField label="Canal" value={sale.channel_label} />
          <InfoField label="Depósito" value={sale.warehouse_name} />
          <InfoField label="Vendedor" value={sale.user_name ?? '—'} />
          <div className="space-y-1.5">
            <InfoField label="Cliente" value={sale.customer_name} />
            {sale.customer_price_list_name && (
              <p className="text-xs text-muted-foreground">
                Lista asignada: {sale.customer_price_list_name}
              </p>
            )}
          </div>
        </div>

        {sale.is_open && (
          <div className="flex flex-col gap-3 lg:flex-row lg:items-start">
            <form onSubmit={handleScanSubmit} className="flex-1 space-y-1.5">
              <Label htmlFor="code">Código de barras o código interno</Label>
              <div className="flex gap-2">
                <div className="relative flex-1">
                  <ScanBarcode className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                  <Input
                    id="code"
                    ref={codeInputRef}
                    autoFocus
                    autoComplete="off"
                    value={code}
                    onChange={(e) => setCode(e.target.value)}
                    placeholder="Escaneá o escribí el código y presioná Enter"
                    className="pl-9"
                    disabled={isAdding}
                  />
                </div>
                <Button type="submit" disabled={isAdding || code.trim() === ''}>
                  {isAdding && (
                    <Loader2 className="mr-1.5 size-4 animate-spin" />
                  )}
                  Agregar
                </Button>
              </div>
              <InputError message={codeError} />
            </form>

            <div className="space-y-1.5 lg:w-96">
              <Label>Buscar artículo</Label>
              <ArticleSearch
                disabled={isAdding}
                onSelect={(article) => addArticle({ article_id: article.id })}
              />
            </div>
          </div>
        )}

        <div className="overflow-x-auto rounded-xl border border-sidebar-border bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Artículo</TableHead>
                <TableHead className="w-36">Cantidad</TableHead>
                <TableHead className="text-right">Precio unitario</TableHead>
                <TableHead>Lista de origen</TableHead>
                <TableHead className="text-right">Total</TableHead>
                {sale.is_open && <TableHead className="w-12" />}
              </TableRow>
            </TableHeader>
            <TableBody>
              {sale.items.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={sale.is_open ? 6 : 5}
                    className="py-10 text-center text-muted-foreground"
                  >
                    Todavía no hay artículos en la venta.
                  </TableCell>
                </TableRow>
              ) : (
                sale.items.map((item) => (
                  <SaleItemRow
                    // Remount when the server changes the quantity (e.g. a new scan).
                    key={`${item.id}-${item.quantity}`}
                    saleId={sale.id}
                    item={item}
                    isEditable={sale.is_open}
                  />
                ))
              )}
            </TableBody>
            <TableFooter>
              <TableRow>
                <TableCell colSpan={4} className="text-right font-semibold">
                  Total
                </TableCell>
                <TableCell className="text-right text-lg font-bold">
                  {formatCurrency(sale.total_amount)}
                </TableCell>
                {sale.is_open && <TableCell />}
              </TableRow>
            </TableFooter>
          </Table>
        </div>

        <p className="text-xs text-muted-foreground">
          Los precios de lista son finales con IVA incluido. El precio de cada
          línea se toma de la lista que corresponde según el cliente y el canal,
          y queda fijo al agregarla.
        </p>
      </div>

      <Dialog open={isDiscardDialogOpen} onOpenChange={setIsDiscardDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>¿Descartar la venta N° {sale.id}?</DialogTitle>
            <DialogDescription>
              La venta queda descartada y ya no se puede modificar.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setIsDiscardDialogOpen(false)}
            >
              Volver
            </Button>
            <Button variant="destructive" onClick={handleDiscard}>
              Descartar venta
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

function InfoField({ label, value }: { label: string; value: string }) {
  return (
    <div className="space-y-1.5">
      <p className="text-sm font-medium text-muted-foreground">{label}</p>
      <p className="text-sm font-medium">{value}</p>
    </div>
  );
}

function SaleItemRow({
  saleId,
  item,
  isEditable,
}: {
  saleId: number;
  item: SaleItem;
  isEditable: boolean;
}) {
  const [quantity, setQuantity] = useState(Number(item.quantity).toString());

  const saveQuantity = () => {
    if (Number(quantity) === Number(item.quantity)) {
      return;
    }

    router.patch(
      updateItem.url({ sale: saleId, item: item.id }),
      { quantity },
      {
        preserveScroll: true,
        onError: (errors) => {
          setQuantity(Number(item.quantity).toString());
          toastFirstError(errors);
        },
      },
    );
  };

  const removeItem = () => {
    router.delete(destroyItem.url({ sale: saleId, item: item.id }), {
      preserveScroll: true,
      onError: toastFirstError,
    });
  };

  return (
    <TableRow>
      <TableCell>
        <div className="flex flex-col">
          <span className="font-mono text-xs text-muted-foreground">
            {item.article_internal_code}
          </span>
          <span>{item.article_description}</span>
        </div>
      </TableCell>
      <TableCell>
        {isEditable ? (
          <div className="flex items-center gap-1.5">
            <Input
              type="number"
              min={item.allows_decimal_quantity ? '0.001' : '1'}
              step={item.allows_decimal_quantity ? '0.001' : '1'}
              value={quantity}
              onChange={(e) => setQuantity(e.target.value)}
              onBlur={saveQuantity}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.currentTarget.blur();
                }
              }}
              className="h-8 w-24"
              aria-label={`Cantidad de ${item.article_description}`}
            />
            <span className="text-xs text-muted-foreground">
              {item.unit_of_measure}
            </span>
          </div>
        ) : (
          <span>
            {formatQuantity(item.quantity, item.allows_decimal_quantity)}{' '}
            {item.unit_of_measure}
          </span>
        )}
      </TableCell>
      <TableCell className="text-right">
        {formatCurrency(item.unit_price)}
      </TableCell>
      <TableCell>
        <Badge
          variant="outline"
          className={priceOriginClasses[item.price_list_scope]}
          title={item.price_list_name}
        >
          {item.price_origin_label}
        </Badge>
      </TableCell>
      <TableCell className="text-right font-medium">
        {formatCurrency(item.line_total)}
      </TableCell>
      {isEditable && (
        <TableCell>
          <Button
            variant="ghost"
            size="icon"
            className="size-8 text-muted-foreground hover:text-destructive"
            onClick={removeItem}
            aria-label={`Quitar ${item.article_description}`}
          >
            <Trash2 className="size-4" />
          </Button>
        </TableCell>
      )}
    </TableRow>
  );
}

function ArticleSearch({
  disabled,
  onSelect,
}: {
  disabled: boolean;
  onSelect: (article: ArticleOption) => void;
}) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const [results, setResults] = useState<ArticleOption[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [hasSearched, setHasSearched] = useState(false);

  useEffect(() => {
    const term = search.trim();

    if (term.length < 2) {
      return;
    }

    const abortController = new AbortController();
    const timer = window.setTimeout(async () => {
      setIsSearching(true);

      try {
        const response = await fetch(
          searchArticles.url({ query: { search: term } }),
          {
            headers: { Accept: 'application/json' },
            signal: abortController.signal,
          },
        );

        setResults(
          response.ok ? ((await response.json()) as ArticleOption[]) : [],
        );
      } catch (error) {
        if (!(error instanceof DOMException && error.name === 'AbortError')) {
          setResults([]);
        }
      } finally {
        if (!abortController.signal.aborted) {
          setIsSearching(false);
          setHasSearched(true);
        }
      }
    }, 300);

    return () => {
      window.clearTimeout(timer);
      abortController.abort();
    };
  }, [search]);

  const updateSearch = (value: string) => {
    setSearch(value);

    if (value.trim().length < 2) {
      setResults([]);
      setHasSearched(false);
      setIsSearching(false);
    }
  };

  const chooseArticle = (article: ArticleOption) => {
    onSelect(article);
    setOpen(false);
    setSearch('');
    setResults([]);
    setHasSearched(false);
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          type="button"
          variant="outline"
          role="combobox"
          aria-expanded={open}
          disabled={disabled}
          className="w-full justify-between font-normal text-muted-foreground"
        >
          <span className="flex items-center gap-2 truncate">
            <Search className="size-4" />
            Buscar por descripción o código
          </span>
          <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent
        className="w-[var(--radix-popover-trigger-width)] p-0"
        align="start"
      >
        <Command shouldFilter={false}>
          <CommandInput
            value={search}
            onValueChange={updateSearch}
            placeholder="Escribí al menos 2 caracteres"
          />
          <CommandList>
            {isSearching && (
              <div className="flex items-center gap-2 px-3 py-4 text-sm text-muted-foreground">
                <Loader2 className="size-4 animate-spin" />
                Buscando…
              </div>
            )}
            {!isSearching && hasSearched && results.length === 0 && (
              <p className="px-3 py-4 text-center text-sm text-muted-foreground">
                No se encontraron artículos activos.
              </p>
            )}
            {results.map((article) => (
              <CommandItem
                key={article.id}
                value={String(article.id)}
                onSelect={() => chooseArticle(article)}
                className="flex-col items-start gap-0.5"
              >
                <span className="font-mono text-xs font-semibold">
                  {article.internal_code}
                  {article.barcode ? ` · ${article.barcode}` : ''}
                </span>
                <span className="text-sm">{article.description}</span>
              </CommandItem>
            ))}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}

SaleShow.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Ventas', href: index() },
    { title: 'Detalle', href: '#' },
  ] satisfies BreadcrumbItem[],
};
