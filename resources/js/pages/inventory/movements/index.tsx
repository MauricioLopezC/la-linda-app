import { Head, Link, router } from '@inertiajs/react';
import {
  ArrowUpRight,
  ChevronsUpDown,
  FileText,
  FilterX,
  Loader2,
  Package,
  User,
  Warehouse as WarehouseIcon,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Command,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from '@/components/ui/pagination';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
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
import { show as showAdjustment } from '@/routes/inventory/adjustments';
import {
  articles as searchArticles,
  index,
} from '@/routes/inventory/movements';
import { show as showSupplierVoucher } from '@/routes/purchasing/vouchers';
import type { BreadcrumbItem } from '@/types';

type StockMovementList = App.Data.Inventory.StockMovementListData;
type KardexEntry = App.Data.Inventory.KardexEntryData;
type ArticleOption = App.Data.Inventory.ArticleStockOptionData;
type Warehouse = App.Data.Inventory.WarehouseData;
type MovementType = App.Data.Inventory.StockMovementTypeData;
type UserOption = App.Data.Inventory.UserOptionData;

type Paginated<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
  links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
  movements: Paginated<StockMovementList> | null;
  kardex: Paginated<KardexEntry> | null;
  currentBalance: string | null;
  article: ArticleOption | null;
  warehouses: Warehouse[];
  movementTypes: MovementType[];
  users: UserOption[];
  filters: {
    article_id?: number | null;
    warehouse_id?: number | null;
    stock_movement_type_id?: number | null;
    user_id?: number | null;
    date_from?: string | null;
    date_to?: string | null;
  };
};

export default function StockMovementHistoryIndex({
  movements,
  kardex,
  currentBalance,
  article,
  warehouses = [],
  movementTypes = [],
  users = [],
  filters,
}: Props) {
  const [articleId, setArticleId] = useState<string>(
    filters.article_id ? String(filters.article_id) : '',
  );
  const [selectedWarehouse, setSelectedWarehouse] = useState<string>(
    filters.warehouse_id ? String(filters.warehouse_id) : 'all',
  );
  const [selectedType, setSelectedType] = useState<string>(
    filters.stock_movement_type_id
      ? String(filters.stock_movement_type_id)
      : 'all',
  );
  const [selectedUser, setSelectedUser] = useState<string>(
    filters.user_id ? String(filters.user_id) : 'all',
  );
  const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
  const [dateTo, setDateTo] = useState(filters.date_to ?? '');

  const applyFilters = useCallback(
    (newFilters: {
      article_id?: string;
      warehouse_id?: string;
      stock_movement_type_id?: string;
      user_id?: string;
      date_from?: string;
      date_to?: string;
    }) => {
      const current = {
        article_id: newFilters.article_id ?? articleId,
        warehouse_id: newFilters.warehouse_id ?? selectedWarehouse,
        stock_movement_type_id:
          newFilters.stock_movement_type_id ?? selectedType,
        user_id: newFilters.user_id ?? selectedUser,
        date_from: newFilters.date_from ?? dateFrom,
        date_to: newFilters.date_to ?? dateTo,
      };

      const queryParams: Record<string, string> = {};

      if (current.article_id) {
        queryParams.article_id = current.article_id;
      }

      if (current.warehouse_id && current.warehouse_id !== 'all') {
        queryParams.warehouse_id = current.warehouse_id;
      }

      if (
        current.stock_movement_type_id &&
        current.stock_movement_type_id !== 'all'
      ) {
        queryParams.stock_movement_type_id = current.stock_movement_type_id;
      }

      if (current.user_id && current.user_id !== 'all') {
        queryParams.user_id = current.user_id;
      }

      if (
        current.date_from &&
        current.date_to &&
        current.date_from > current.date_to
      ) {
        toast.error('La fecha "Desde" no puede ser mayor a la fecha "Hasta"');

        return;
      }

      if (current.date_from) {
        queryParams.date_from = current.date_from;
      }

      if (current.date_to) {
        queryParams.date_to = current.date_to;
      }

      router.get(
        index.url({ query: queryParams }),
        {},
        {
          preserveState: true,
          preserveScroll: true,
          replace: true,
        },
      );
    },
    [
      articleId,
      selectedWarehouse,
      selectedType,
      selectedUser,
      dateFrom,
      dateTo,
    ],
  );

  const handleClearFilters = () => {
    setArticleId('');
    setSelectedWarehouse('all');
    setSelectedType('all');
    setSelectedUser('all');
    setDateFrom('');
    setDateTo('');

    router.get(
      index.url(),
      {},
      {
        preserveState: true,
        preserveScroll: true,
        replace: true,
      },
    );
  };

  const goToPage = (url: string | null) => {
    if (!url) {
      return;
    }

    router.get(
      url,
      {},
      {
        preserveState: true,
        preserveScroll: true,
        replace: true,
      },
    );
  };

  const handleArticleChange = (selected: ArticleOption | null) => {
    const value = selected ? String(selected.id) : '';
    setArticleId(value);
    applyFilters({ article_id: value });
  };

  const hasActiveFilters =
    articleId !== '' ||
    selectedWarehouse !== 'all' ||
    selectedType !== 'all' ||
    selectedUser !== 'all' ||
    dateFrom !== '' ||
    dateTo !== '';

  const page = kardex ?? movements;
  const unitName = article?.unit_of_measure_name ?? '';
  const selectedWarehouseData = warehouses.find(
    (wh) => String(wh.id) === selectedWarehouse,
  );

  return (
    <>
      <Head title="Historial de Movimientos" />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <Heading
            title="Historial de Movimientos"
            description="Consulta el registro histórico de operaciones de inventario, ajustes y transferencias."
          />
        </div>

        {/* Filters */}
        <div className="flex flex-col gap-3 rounded-xl border border-sidebar-border bg-card p-4 shadow-xs">
          <div className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7">
            <div className="min-w-0 xl:col-span-2">
              <ArticleFilter
                selected={article}
                onSelect={handleArticleChange}
              />
            </div>

            <div className="min-w-0">
              <Select
                value={selectedWarehouse}
                onValueChange={(val) => {
                  setSelectedWarehouse(val);
                  applyFilters({ warehouse_id: val });
                }}
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Todos los depósitos" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todos los depósitos</SelectItem>
                  {warehouses.map((wh) => (
                    <SelectItem key={wh.id} value={String(wh.id)}>
                      {wh.name} ({wh.branch_name})
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="min-w-0">
              <Select
                value={selectedType}
                onValueChange={(val) => {
                  setSelectedType(val);
                  applyFilters({ stock_movement_type_id: val });
                }}
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Todos los tipos" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todos los tipos</SelectItem>
                  {movementTypes.map((type) => (
                    <SelectItem key={type.id} value={String(type.id)}>
                      {type.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="min-w-0">
              <Select
                value={selectedUser}
                onValueChange={(val) => {
                  setSelectedUser(val);
                  applyFilters({ user_id: val });
                }}
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Todos los usuarios" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todos los usuarios</SelectItem>
                  {users.map((user) => (
                    <SelectItem key={user.id} value={String(user.id)}>
                      {user.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="flex min-w-0 gap-2 xl:col-span-2">
              <Input
                type="date"
                value={dateFrom}
                max={dateTo || undefined}
                onChange={(e) => {
                  setDateFrom(e.target.value);
                  applyFilters({ date_from: e.target.value });
                }}
                className="w-full"
                title="Fecha desde"
              />
              <Input
                type="date"
                value={dateTo}
                min={dateFrom || undefined}
                onChange={(e) => {
                  setDateTo(e.target.value);
                  applyFilters({ date_to: e.target.value });
                }}
                className="w-full"
                title="Fecha hasta"
              />
            </div>
          </div>

          {article && !kardex && (
            <p className="border-t border-sidebar-border pt-2 text-xs text-muted-foreground">
              Elegí un depósito para ver el kardex con el saldo de este
              artículo.
            </p>
          )}

          {hasActiveFilters && (
            <div className="flex items-center justify-between border-t border-sidebar-border pt-2">
              <span className="text-xs text-muted-foreground">
                Filtros activos aplicados
              </span>
              <Button
                variant="ghost"
                size="sm"
                onClick={handleClearFilters}
                className="h-8 text-xs text-muted-foreground hover:text-foreground"
              >
                <FilterX className="mr-1.5 size-3.5" />
                Limpiar filtros
              </Button>
            </div>
          )}
        </div>

        {/* Kardex */}
        {kardex && article && (
          <div className="grid grid-cols-1 gap-3 rounded-xl border border-sidebar-border bg-card p-4 shadow-xs sm:grid-cols-3">
            <div>
              <div className="text-xs text-muted-foreground">Artículo</div>
              <div className="font-medium">{article.description}</div>
              <div className="font-mono text-xs text-muted-foreground">
                {article.internal_code}
              </div>
            </div>
            <div>
              <div className="text-xs text-muted-foreground">Depósito</div>
              <div className="font-medium">
                {selectedWarehouseData?.name ?? '-'}
              </div>
              <div className="text-xs text-muted-foreground">
                {selectedWarehouseData?.branch_name}
              </div>
            </div>
            <div className="sm:text-right">
              <div className="text-xs text-muted-foreground">Stock actual</div>
              <div className="font-mono text-2xl font-bold">
                {formatStockQuantity(
                  parseFloat(currentBalance ?? '0'),
                  unitName,
                )}
              </div>
            </div>
          </div>
        )}

        {kardex ? (
          <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Fecha</TableHead>
                  <TableHead>Tipo</TableHead>
                  <TableHead>Documento</TableHead>
                  <TableHead className="text-right">Entrada</TableHead>
                  <TableHead className="text-right">Salida</TableHead>
                  <TableHead className="text-right">Saldo</TableHead>
                  <TableHead>Usuario</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {kardex.data.length === 0 ? (
                  <TableRow>
                    <TableCell
                      colSpan={7}
                      className="py-12 text-center text-muted-foreground"
                    >
                      Este artículo no tiene movimientos en el depósito para los
                      filtros seleccionados.
                    </TableCell>
                  </TableRow>
                ) : (
                  kardex.data.map((entry) => {
                    const delta = parseFloat(entry.quantity);

                    return (
                      <TableRow key={entry.id}>
                        <TableCell className="font-mono text-sm">
                          {entry.created_at_formatted}
                        </TableCell>
                        <TableCell>
                          <Badge variant="secondary" className="font-normal">
                            {entry.type_name}
                          </Badge>
                        </TableCell>
                        <TableCell>
                          <div className="flex flex-col gap-1">
                            <Link
                              href={showAdjustment({
                                stock_movement: entry.stock_movement_id,
                              })}
                              className="dark:text-primary-400 flex items-center gap-1 text-xs text-primary-600 hover:underline"
                            >
                              <FileText className="size-3" />
                              Comprobante #{entry.stock_movement_id}
                            </Link>
                            {entry.supplier_voucher_id && (
                              <Link
                                href={showSupplierVoucher({
                                  supplier_voucher: entry.supplier_voucher_id,
                                })}
                                className="flex items-center gap-1 text-xs text-blue-600 hover:underline dark:text-blue-400"
                              >
                                <ArrowUpRight className="size-3" />
                                Remito{' '}
                                {entry.supplier_voucher_formatted_number ??
                                  `#${entry.supplier_voucher_id}`}
                              </Link>
                            )}
                          </div>
                        </TableCell>
                        <TableCell className="text-right font-mono font-medium text-emerald-600">
                          {delta > 0
                            ? `+${formatStockQuantity(delta, unitName)}`
                            : ''}
                        </TableCell>
                        <TableCell className="text-right font-mono font-medium text-rose-600">
                          {delta < 0
                            ? formatStockQuantity(delta, unitName)
                            : ''}
                        </TableCell>
                        <TableCell className="text-right font-mono font-bold">
                          {formatStockQuantity(
                            parseFloat(entry.balance),
                            unitName,
                          )}
                        </TableCell>
                        <TableCell>
                          <div className="flex items-center gap-1.5 text-sm">
                            <User className="size-3.5 text-muted-foreground" />
                            {entry.user_name}
                          </div>
                        </TableCell>
                      </TableRow>
                    );
                  })
                )}
              </TableBody>
            </Table>
          </div>
        ) : (
          <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Fecha</TableHead>
                  <TableHead>Tipo</TableHead>
                  <TableHead>Depósito</TableHead>
                  <TableHead className="text-right">Artículos</TableHead>
                  <TableHead className="text-right">Volumen total</TableHead>
                  <TableHead>Usuario</TableHead>
                  <TableHead>Detalle / Doc</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {movements && movements.data.length === 0 ? (
                  <TableRow>
                    <TableCell
                      colSpan={7}
                      className="py-12 text-center text-muted-foreground"
                    >
                      No se encontraron movimientos para los filtros
                      seleccionados.
                    </TableCell>
                  </TableRow>
                ) : (
                  movements?.data.map((movement) => (
                    <TableRow key={movement.id}>
                      <TableCell className="font-mono text-sm">
                        {movement.created_at_formatted}
                      </TableCell>
                      <TableCell>
                        <Badge variant="secondary" className="font-normal">
                          {movement.type_name}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center gap-1.5">
                          <WarehouseIcon className="size-3.5 text-muted-foreground" />
                          <span className="font-medium">
                            {movement.warehouse_name}
                          </span>
                        </div>
                        <span className="text-xs text-muted-foreground">
                          {movement.branch_name}
                        </span>
                      </TableCell>
                      <TableCell className="text-right text-sm">
                        <div className="flex items-center justify-end gap-1 text-muted-foreground">
                          <Package className="size-3.5" />
                          <span>{movement.items_count}</span>
                        </div>
                      </TableCell>
                      <TableCell className="text-right font-mono font-medium">
                        {movement.total_quantity}
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center gap-1.5 text-sm">
                          <User className="size-3.5 text-muted-foreground" />
                          {movement.user_name}
                        </div>
                      </TableCell>
                      <TableCell>
                        <div className="flex flex-col gap-1">
                          <span className="text-sm text-muted-foreground">
                            {movement.notes || '-'}
                          </span>
                          <div className="flex flex-wrap items-center gap-2">
                            <Link
                              href={showAdjustment({
                                stock_movement: movement.id,
                              })}
                              className="dark:text-primary-400 flex items-center gap-1 text-xs text-primary-600 hover:underline"
                            >
                              <FileText className="size-3" />
                              Ver comprobante #{movement.id}
                            </Link>
                            {movement.supplier_voucher_id && (
                              <Link
                                href={showSupplierVoucher({
                                  supplier_voucher:
                                    movement.supplier_voucher_id,
                                })}
                                className="flex items-center gap-1 text-xs text-blue-600 hover:underline dark:text-blue-400"
                              >
                                <ArrowUpRight className="size-3" />
                                Remito{' '}
                                {movement.supplier_voucher_formatted_number ??
                                  `#${movement.supplier_voucher_id}`}
                              </Link>
                            )}
                          </div>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </div>
        )}

        {/* Pagination */}
        {page && page.last_page > 1 && (
          <div className="flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
            <p className="text-sm text-muted-foreground">
              Mostrando {page.from ?? 0}–{page.to ?? 0} de {page.total}{' '}
              movimientos
            </p>
            <Pagination className="mx-0 w-auto">
              <PaginationContent>
                <PaginationItem>
                  <PaginationPrevious
                    href={page.links[0]?.url ?? '#'}
                    aria-disabled={!page.links[0]?.url}
                    className={
                      !page.links[0]?.url
                        ? 'pointer-events-none opacity-50'
                        : undefined
                    }
                    onClick={(e) => {
                      e.preventDefault();
                      goToPage(page.links[0]?.url ?? null);
                    }}
                  />
                </PaginationItem>

                {page.links.slice(1, -1).map((link, index) =>
                  link.url === null ? (
                    <PaginationItem key={`ellipsis-${index}`}>
                      <PaginationEllipsis />
                    </PaginationItem>
                  ) : (
                    <PaginationItem key={link.label}>
                      <PaginationLink
                        href={link.url}
                        isActive={link.active}
                        onClick={(e) => {
                          e.preventDefault();
                          goToPage(link.url);
                        }}
                      >
                        {link.label}
                      </PaginationLink>
                    </PaginationItem>
                  ),
                )}

                <PaginationItem>
                  <PaginationNext
                    href={page.links[page.links.length - 1]?.url ?? '#'}
                    aria-disabled={!page.links[page.links.length - 1]?.url}
                    className={
                      !page.links[page.links.length - 1]?.url
                        ? 'pointer-events-none opacity-50'
                        : undefined
                    }
                    onClick={(e) => {
                      e.preventDefault();
                      goToPage(page.links[page.links.length - 1]?.url ?? null);
                    }}
                  />
                </PaginationItem>
              </PaginationContent>
            </Pagination>
          </div>
        )}
      </div>
    </>
  );
}

function ArticleFilter({
  selected,
  onSelect,
}: {
  selected: ArticleOption | null;
  onSelect: (article: ArticleOption | null) => void;
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

        if (!response.ok) {
          setResults([]);

          return;
        }

        setResults((await response.json()) as ArticleOption[]);
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

  const chooseArticle = (article: ArticleOption | null) => {
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
          className="w-full justify-between font-normal"
        >
          <span className="flex min-w-0 items-center gap-2">
            <Package className="size-4 shrink-0 text-muted-foreground" />
            <span className="truncate">
              {selected
                ? `${selected.internal_code} · ${selected.description}`
                : 'Todos los artículos'}
            </span>
          </span>
          <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent
        className="w-[var(--radix-popover-trigger-width)] min-w-72 p-0"
        align="start"
      >
        <Command shouldFilter={false}>
          <CommandInput
            value={search}
            onValueChange={updateSearch}
            placeholder="Buscar por código, descripción o barras"
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
                No se encontraron artículos.
              </p>
            )}
            {selected && (
              <CommandItem
                value="__todos_los_articulos__"
                onSelect={() => chooseArticle(null)}
              >
                Todos los artículos
              </CommandItem>
            )}
            {results.map((option) => (
              <CommandItem
                key={option.id}
                value={String(option.id)}
                onSelect={() => chooseArticle(option)}
                className="flex-col items-start gap-0.5"
              >
                <span className="font-mono text-xs font-semibold">
                  {option.internal_code}
                </span>
                <span className="text-sm">{option.description}</span>
              </CommandItem>
            ))}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}

StockMovementHistoryIndex.layout = {
  breadcrumbs: [
    {
      title: 'Dashboard',
      href: '/dashboard',
    },
    {
      title: 'Historial de Movimientos',
      href: '/inventory/movements',
    },
  ] satisfies BreadcrumbItem[],
};
