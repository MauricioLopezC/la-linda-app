import { Head, Link, router } from '@inertiajs/react';
import {
  FileText,
  FilterX,
  Package,
  Search,
  User,
  Warehouse as WarehouseIcon,
} from 'lucide-react';
import { useCallback, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { show as showAdjustment } from '@/routes/inventory/adjustments';
import { index } from '@/routes/inventory/movements';
import type { BreadcrumbItem } from '@/types';

type StockMovementList = App.Data.Inventory.StockMovementListData;
type Warehouse = App.Data.Inventory.WarehouseData;
type MovementType = App.Data.Inventory.StockMovementTypeData;
type UserOption = App.Data.Inventory.UserOptionData;

type StockMovementPage = {
  data: StockMovementList[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
  links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
  movements: StockMovementPage;
  warehouses: Warehouse[];
  movementTypes: MovementType[];
  users: UserOption[];
  filters: {
    search?: string | null;
    warehouse_id?: number | null;
    stock_movement_type_id?: number | null;
    user_id?: number | null;
    date_from?: string | null;
    date_to?: string | null;
  };
};

export default function StockMovementHistoryIndex({
  movements,
  warehouses = [],
  movementTypes = [],
  users = [],
  filters,
}: Props) {
  const [searchTerm, setSearchTerm] = useState(filters.search ?? '');
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
      search?: string;
      warehouse_id?: string;
      stock_movement_type_id?: string;
      user_id?: string;
      date_from?: string;
      date_to?: string;
    }) => {
      const current = {
        search: newFilters.search ?? searchTerm,
        warehouse_id: newFilters.warehouse_id ?? selectedWarehouse,
        stock_movement_type_id:
          newFilters.stock_movement_type_id ?? selectedType,
        user_id: newFilters.user_id ?? selectedUser,
        date_from: newFilters.date_from ?? dateFrom,
        date_to: newFilters.date_to ?? dateTo,
      };

      const queryParams: Record<string, string> = {};

      if (current.search.trim()) {
        queryParams.search = current.search.trim();
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
      searchTerm,
      selectedWarehouse,
      selectedType,
      selectedUser,
      dateFrom,
      dateTo,
    ],
  );

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters({ search: searchTerm });
  };

  const handleClearFilters = () => {
    setSearchTerm('');
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

  const hasActiveFilters =
    searchTerm.trim() !== '' ||
    selectedWarehouse !== 'all' ||
    selectedType !== 'all' ||
    selectedUser !== 'all' ||
    dateFrom !== '' ||
    dateTo !== '';

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
          <form
            onSubmit={handleSearchSubmit}
            className="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7"
          >
            <div className="relative xl:col-span-2">
              <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder="Buscar artículo..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                onBlur={() => applyFilters({ search: searchTerm })}
                className="pl-9"
              />
            </div>

            <div>
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

            <div>
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

            <div>
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

            <div className="flex gap-2 xl:col-span-2">
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

        {/* Table */}
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
                <TableHead>Motivo / Doc</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {movements.data.length === 0 ? (
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
                movements.data.map((movement) => (
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
                      {movement.type_code === 'inventory_adjustment' ? (
                        <div className="flex flex-col gap-1">
                          <span className="text-sm font-medium">
                            {movement.reason_name}
                          </span>
                          <Link
                            href={showAdjustment({
                              stock_movement: movement.id,
                            })}
                            className="dark:text-primary-400 flex items-center gap-1 text-xs text-primary-600 hover:underline"
                          >
                            <FileText className="size-3" />
                            Ver comprobante #{movement.id}
                          </Link>
                        </div>
                      ) : (
                        <span className="text-sm text-muted-foreground">
                          {movement.notes || '-'}
                        </span>
                      )}
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>

        {/* Pagination */}
        {movements.last_page > 1 && (
          <div className="flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
            <p className="text-sm text-muted-foreground">
              Mostrando {movements.from ?? 0}–{movements.to ?? 0} de{' '}
              {movements.total} movimientos
            </p>
            <Pagination className="mx-0 w-auto">
              <PaginationContent>
                <PaginationItem>
                  <PaginationPrevious
                    href={movements.links[0]?.url ?? '#'}
                    aria-disabled={!movements.links[0]?.url}
                    className={
                      !movements.links[0]?.url
                        ? 'pointer-events-none opacity-50'
                        : undefined
                    }
                    onClick={(e) => {
                      e.preventDefault();
                      goToPage(movements.links[0]?.url ?? null);
                    }}
                  />
                </PaginationItem>

                {movements.links.slice(1, -1).map((link, index) =>
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
                    href={
                      movements.links[movements.links.length - 1]?.url ?? '#'
                    }
                    aria-disabled={
                      !movements.links[movements.links.length - 1]?.url
                    }
                    className={
                      !movements.links[movements.links.length - 1]?.url
                        ? 'pointer-events-none opacity-50'
                        : undefined
                    }
                    onClick={(e) => {
                      e.preventDefault();
                      goToPage(
                        movements.links[movements.links.length - 1]?.url ??
                          null,
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
