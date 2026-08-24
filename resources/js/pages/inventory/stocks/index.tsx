import { Head, router } from '@inertiajs/react';
import {
  Building2,
  CheckCircle2,
  FilterX,
  Layers,
  Search,
  Warehouse as WarehouseIcon,
  XCircle,
} from 'lucide-react';
import { useCallback, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import { index } from '@/routes/inventory/stocks';
import type { BreadcrumbItem } from '@/types';

type StockBalance = App.Data.Inventory.StockBalanceData;

/**
 * Shape of a `spatie/laravel-data` `PaginatedDataCollection`, which wraps
 * `Illuminate\Pagination\LengthAwarePaginator::toArray()` verbatim: flat pagination
 * fields alongside `data`, not nested under a `meta` key. The auto-generated
 * `Spatie.LaravelData.PaginatedDataCollection` TS type models the API-resource-style
 * nested `{ data, links, meta }` shape instead, which doesn't match this runtime output.
 */
type StockBalancePage = {
  data: StockBalance[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
  links: { url: string | null; label: string; active: boolean }[];
};
type StockTotals = App.Data.Inventory.StockTotalsData;
type Category = App.Data.Catalog.CategoryData;
type Warehouse = App.Data.Inventory.WarehouseData;

type Props = {
  stocks: StockBalancePage;
  totals: StockTotals;
  categories: Category[];
  warehouses: Warehouse[];
  filters: {
    search?: string | null;
    category_id?: number | null;
    warehouse_id?: number | null;
    status?: string | null;
  };
};

export default function StockConsultationIndex({
  stocks,
  totals,
  categories = [],
  warehouses = [],
  filters,
}: Props) {
  const [searchTerm, setSearchTerm] = useState(filters.search ?? '');
  const [selectedCategory, setSelectedCategory] = useState<string>(
    filters.category_id ? String(filters.category_id) : 'all',
  );
  const [selectedWarehouse, setSelectedWarehouse] = useState<string>(
    filters.warehouse_id ? String(filters.warehouse_id) : 'all',
  );
  const [selectedStatus, setSelectedStatus] = useState<string>(
    filters.status ?? 'all',
  );

  const applyFilters = useCallback(
    (newFilters: {
      search?: string;
      category_id?: string;
      warehouse_id?: string;
      status?: string;
    }) => {
      const current = {
        search: newFilters.search ?? searchTerm,
        category_id: newFilters.category_id ?? selectedCategory,
        warehouse_id: newFilters.warehouse_id ?? selectedWarehouse,
        status: newFilters.status ?? selectedStatus,
      };

      const queryParams: Record<string, string> = {};

      if (current.search.trim()) {
        queryParams.search = current.search.trim();
      }

      if (current.category_id && current.category_id !== 'all') {
        queryParams.category_id = current.category_id;
      }

      if (current.warehouse_id && current.warehouse_id !== 'all') {
        queryParams.warehouse_id = current.warehouse_id;
      }

      if (current.status && current.status !== 'all') {
        queryParams.status = current.status;
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
    [searchTerm, selectedCategory, selectedWarehouse, selectedStatus],
  );

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters({ search: searchTerm });
  };

  const handleCategoryChange = (val: string) => {
    setSelectedCategory(val);
    applyFilters({ category_id: val });
  };

  const handleWarehouseChange = (val: string) => {
    setSelectedWarehouse(val);
    applyFilters({ warehouse_id: val });
  };

  const handleStatusChange = (val: string) => {
    setSelectedStatus(val);
    applyFilters({ status: val });
  };

  const handleClearFilters = () => {
    setSearchTerm('');
    setSelectedCategory('all');
    setSelectedWarehouse('all');
    setSelectedStatus('all');

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

  const hasActiveFilters =
    searchTerm.trim() !== '' ||
    selectedCategory !== 'all' ||
    selectedWarehouse !== 'all' ||
    selectedStatus !== 'all';

  return (
    <>
      <Head title="Existencias por Depósito" />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        {/* Header */}
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <Heading
            title="Existencias por Depósito"
            description="Consulta el inventario físico disponible en cada depósito y sucursal en modo de solo lectura."
          />
        </div>

        {/* Global Summary Cards (Consolidated Operational Metrics) */}
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">
                Artículos / SKUs Registrados
              </CardTitle>
              <Layers className="size-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-foreground">
                {totals?.grand_total_items ?? 0}
              </div>
              <p className="mt-1 text-xs text-muted-foreground">
                Total de registros de stock consultados
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">
                Con Existencia (Disponibles)
              </CardTitle>
              <CheckCircle2 className="size-4 text-emerald-600 dark:text-emerald-400" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                {totals?.total_in_stock ?? 0}
              </div>
              <p className="mt-1 text-xs text-muted-foreground">
                Artículos con stock mayor a cero
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">
                Sin Stock (Agotados)
              </CardTitle>
              <XCircle className="size-4 text-destructive" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-destructive">
                {totals?.total_out_of_stock ?? 0}
              </div>
              <p className="mt-1 text-xs text-muted-foreground">
                Artículos con existencia en cero
              </p>
            </CardContent>
          </Card>
        </div>

        {/* Branch Totals Breakdown */}
        {totals?.branch_totals && totals.branch_totals.length > 0 && (
          <div className="flex flex-col gap-3">
            <div className="flex items-center gap-2 text-sm font-semibold text-muted-foreground">
              <Building2 className="size-4" />
              <span>Totales por Sucursal</span>
            </div>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {totals.branch_totals.map((branch) => (
                <div
                  key={branch.branch_id}
                  className="flex items-center justify-between rounded-lg border border-sidebar-border bg-card p-3.5 shadow-xs"
                >
                  <div className="flex flex-col">
                    <span className="font-medium text-foreground">
                      {branch.branch_name}
                    </span>
                    <span className="text-xs text-muted-foreground">
                      {branch.total_items} artículos consultados
                    </span>
                  </div>
                  <div className="flex items-center gap-2 text-right">
                    <Badge
                      variant="outline"
                      className="border-emerald-500 bg-emerald-50 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400"
                    >
                      {branch.in_stock_count} con stock
                    </Badge>
                    {branch.out_of_stock_count > 0 && (
                      <Badge
                        variant="destructive"
                        className="text-xs font-semibold"
                      >
                        {branch.out_of_stock_count} sin stock
                      </Badge>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Filters Bar */}
        <div className="flex flex-col gap-3 rounded-xl border border-sidebar-border bg-card p-4 shadow-xs">
          <form
            onSubmit={handleSearchSubmit}
            className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4"
          >
            {/* Search Input */}
            <div className="relative">
              <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder="Buscar por código o descripción..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                onBlur={() => applyFilters({ search: searchTerm })}
                className="pl-9"
              />
            </div>

            {/* Category Select */}
            <div>
              <Select
                value={selectedCategory}
                onValueChange={handleCategoryChange}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Todas las categorías" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todas las categorías</SelectItem>
                  {categories.map((cat) => (
                    <SelectItem key={cat.id} value={String(cat.id)}>
                      {cat.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {/* Warehouse Select */}
            <div>
              <Select
                value={selectedWarehouse}
                onValueChange={handleWarehouseChange}
              >
                <SelectTrigger>
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

            {/* Status Select */}
            <div>
              <Select value={selectedStatus} onValueChange={handleStatusChange}>
                <SelectTrigger>
                  <SelectValue placeholder="Estado de existencias" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todos los estados</SelectItem>
                  <SelectItem value="in_stock">Con stock</SelectItem>
                  <SelectItem value="out_of_stock">
                    Sin stock (Agotados)
                  </SelectItem>
                </SelectContent>
              </Select>
            </div>
          </form>

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

        {/* Stock Balances Table (Read-Only) */}
        <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-[120px]">Código</TableHead>
                <TableHead>Artículo</TableHead>
                <TableHead>Categoría</TableHead>
                <TableHead>Sucursal / Depósito</TableHead>
                <TableHead className="text-right">Existencia</TableHead>
                <TableHead className="w-[140px] text-center">Estado</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {stocks.data.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={6}
                    className="py-12 text-center text-muted-foreground"
                  >
                    No se encontraron existencias para los criterios
                    seleccionados.
                  </TableCell>
                </TableRow>
              ) : (
                stocks.data.map((stock) => (
                  <TableRow key={stock.id}>
                    <TableCell className="font-mono text-xs font-semibold text-muted-foreground">
                      {stock.article_code}
                    </TableCell>
                    <TableCell>
                      <div className="font-medium text-foreground">
                        {stock.article_description}
                      </div>
                      <div className="text-xs text-muted-foreground">
                        {stock.brand_name ? `${stock.brand_name} · ` : ''}
                        {stock.unit_of_measure_name}
                      </div>
                    </TableCell>
                    <TableCell>
                      <Badge variant="secondary" className="font-normal">
                        {stock.category_name}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-1.5">
                        <WarehouseIcon className="size-3.5 text-muted-foreground" />
                        <span className="font-medium">
                          {stock.warehouse_name}
                        </span>
                      </div>
                      <span className="text-xs text-muted-foreground">
                        {stock.branch_name}
                      </span>
                    </TableCell>
                    <TableCell className="text-right font-mono text-base font-bold text-foreground">
                      {formatStockQuantity(
                        stock.quantity,
                        stock.unit_of_measure_name,
                      )}
                    </TableCell>
                    <TableCell className="text-center">
                      {stock.is_out_of_stock ? (
                        <Badge variant="destructive" className="gap-1">
                          <XCircle className="size-3" />
                          Sin stock
                        </Badge>
                      ) : (
                        <Badge
                          variant="outline"
                          className="gap-1 border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400"
                        >
                          <CheckCircle2 className="size-3" />
                          En stock
                        </Badge>
                      )}
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>

        {/* Pagination */}
        {stocks.last_page > 1 && (
          <div className="flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
            <p className="text-sm text-muted-foreground">
              Mostrando {stocks.from ?? 0}–{stocks.to ?? 0} de {stocks.total}{' '}
              existencias
            </p>
            <Pagination className="mx-0 w-auto">
              <PaginationContent>
                <PaginationItem>
                  <PaginationPrevious
                    href={stocks.links[0]?.url ?? '#'}
                    aria-disabled={!stocks.links[0]?.url}
                    className={
                      !stocks.links[0]?.url
                        ? 'pointer-events-none opacity-50'
                        : undefined
                    }
                    onClick={(e) => {
                      e.preventDefault();
                      goToPage(stocks.links[0]?.url ?? null);
                    }}
                  />
                </PaginationItem>

                {stocks.links.slice(1, -1).map((link, index) =>
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
                    href={stocks.links[stocks.links.length - 1]?.url ?? '#'}
                    aria-disabled={!stocks.links[stocks.links.length - 1]?.url}
                    className={
                      !stocks.links[stocks.links.length - 1]?.url
                        ? 'pointer-events-none opacity-50'
                        : undefined
                    }
                    onClick={(e) => {
                      e.preventDefault();
                      goToPage(
                        stocks.links[stocks.links.length - 1]?.url ?? null,
                      );
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

StockConsultationIndex.layout = {
  breadcrumbs: [
    {
      title: 'Dashboard',
      href: '/dashboard',
    },
    {
      title: 'Existencias por Depósito',
      href: '/inventory/stocks',
    },
  ] satisfies BreadcrumbItem[],
};
