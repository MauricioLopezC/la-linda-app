import {
  ArrowUpDown,
  FilterX,
  Search,
  SlidersHorizontal,
  X,
} from 'lucide-react';
import React, { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

export type CatalogFiltersState = {
  search: string;
  category_id: string;
  brand_id: string;
  min_price: string;
  max_price: string;
  sort: string;
};

export type CatalogFiltersProps = {
  categories: App.Data.Catalog.CategoryData[];
  brands: App.Data.Catalog.BrandData[];
  filters: CatalogFiltersState;
  totalArticles: number;
  onApplyFilters: (newFilters: Partial<CatalogFiltersState>) => void;
  onResetFilters: () => void;
};

const SORT_LABELS: Record<string, string> = {
  relevance: 'Más relevantes',
  price_asc: 'Menor precio',
  price_desc: 'Mayor precio',
  name_asc: 'Nombre (A – Z)',
  name_desc: 'Nombre (Z – A)',
};

function SearchBarForm({
  search,
  onSearch,
  onClear,
  onReset,
  isFiltered,
}: {
  search: string;
  onSearch: (search: string) => void;
  onClear: () => void;
  onReset: () => void;
  isFiltered: boolean;
}) {
  const [localSearch, setLocalSearch] = useState(search);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onSearch(localSearch.trim());
  };

  const handleClear = () => {
    setLocalSearch('');
    onClear();
  };

  return (
    <form
      onSubmit={handleSubmit}
      className="flex flex-col gap-2 sm:flex-row sm:items-center"
    >
      <div className="relative flex-1">
        <Search className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          type="text"
          placeholder="Buscar artículos por descripción (ej. leche, arroz, fideos)..."
          value={localSearch}
          onChange={(e) => setLocalSearch(e.target.value)}
          className="pr-9 pl-10"
        />
        {localSearch && (
          <button
            type="button"
            onClick={handleClear}
            aria-label="Limpiar búsqueda"
            className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
          >
            <X className="size-4" />
          </button>
        )}
      </div>
      <div className="flex gap-2">
        <Button type="submit" className="gap-2">
          <Search className="size-4" />
          Buscar
        </Button>
        {isFiltered && (
          <Button
            type="button"
            variant="outline"
            onClick={onReset}
            className="gap-2"
          >
            <FilterX className="size-4" />
            Limpiar filtros
          </Button>
        )}
      </div>
    </form>
  );
}

function PriceRangeForm({
  minPrice,
  maxPrice,
  onApplyPrice,
}: {
  minPrice: string;
  maxPrice: string;
  onApplyPrice: (min: string, max: string) => void;
}) {
  const [localMin, setLocalMin] = useState(minPrice);
  const [localMax, setLocalMax] = useState(maxPrice);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onApplyPrice(localMin.trim(), localMax.trim());
  };

  return (
    <form onSubmit={handleSubmit} className="flex items-center gap-1.5">
      <span className="text-xs font-medium text-muted-foreground">Precio:</span>
      <div className="flex items-center gap-1">
        <div className="relative w-20">
          <span className="absolute top-1/2 left-2 -translate-y-1/2 text-xs text-muted-foreground">
            $
          </span>
          <Input
            type="number"
            min="0"
            step="any"
            placeholder="Mín"
            value={localMin}
            onChange={(e) => setLocalMin(e.target.value)}
            className="h-8 pr-1.5 pl-5 text-xs"
          />
        </div>
        <span className="text-xs text-muted-foreground">–</span>
        <div className="relative w-20">
          <span className="absolute top-1/2 left-2 -translate-y-1/2 text-xs text-muted-foreground">
            $
          </span>
          <Input
            type="number"
            min="0"
            step="any"
            placeholder="Máx"
            value={localMax}
            onChange={(e) => setLocalMax(e.target.value)}
            className="h-8 pr-1.5 pl-5 text-xs"
          />
        </div>
        <Button
          type="submit"
          variant="secondary"
          size="sm"
          className="h-8 px-2.5 text-xs"
          title="Aplicar rango de precio"
        >
          Aplicar
        </Button>
      </div>
    </form>
  );
}

export function CatalogFilters({
  categories,
  brands,
  filters,
  totalArticles,
  onApplyFilters,
  onResetFilters,
}: CatalogFiltersProps) {
  // Resolve active filter labels for chips
  const activeCategory =
    filters.category_id && filters.category_id !== 'all'
      ? categories.find((c) => String(c.id) === filters.category_id)
      : null;

  const activeBrand =
    filters.brand_id && filters.brand_id !== 'all'
      ? brands.find((b) => String(b.id) === filters.brand_id)
      : null;

  const hasSearch = Boolean(filters.search);
  const hasCategory = Boolean(activeCategory);
  const hasBrand = Boolean(activeBrand);
  const hasMinPrice = Boolean(filters.min_price);
  const hasMaxPrice = Boolean(filters.max_price);
  const hasCustomSort = Boolean(filters.sort && filters.sort !== 'relevance');

  const isFiltered =
    hasSearch ||
    hasCategory ||
    hasBrand ||
    hasMinPrice ||
    hasMaxPrice ||
    hasCustomSort;

  return (
    <section className="flex flex-col gap-4 rounded-xl border border-border bg-card p-4 shadow-xs">
      {/* Search Bar Form */}
      <SearchBarForm
        key={filters.search}
        search={filters.search || ''}
        onSearch={(search) => onApplyFilters({ search })}
        onClear={() => onApplyFilters({ search: '' })}
        onReset={onResetFilters}
        isFiltered={isFiltered}
      />

      {/* Categories Pill Navigation */}
      {categories.length > 0 && (
        <div className="flex flex-col gap-2 border-t border-border/60 pt-2">
          <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
            Categorías:
          </span>
          <div className="flex flex-wrap gap-1.5 overflow-x-auto pb-1">
            <Button
              type="button"
              size="sm"
              variant={
                !filters.category_id || filters.category_id === 'all'
                  ? 'default'
                  : 'outline'
              }
              onClick={() => onApplyFilters({ category_id: 'all' })}
              className="h-8 rounded-full text-xs font-medium"
            >
              Todas las categorías
            </Button>
            {categories.map((category) => (
              <Button
                key={category.id}
                type="button"
                size="sm"
                variant={
                  filters.category_id === String(category.id)
                    ? 'default'
                    : 'outline'
                }
                onClick={() =>
                  onApplyFilters({ category_id: String(category.id) })
                }
                className="h-8 rounded-full text-xs font-medium"
              >
                {category.name}
              </Button>
            ))}
          </div>
        </div>
      )}

      {/* Advanced Filters: Brand, Price Range, and Sort Controls */}
      <div className="flex flex-col gap-3 border-t border-border/60 pt-3 md:flex-row md:items-center md:justify-between">
        <div className="flex flex-wrap items-center gap-3">
          {/* Brand Selector */}
          <div className="flex items-center gap-1.5">
            <span className="text-xs font-medium text-muted-foreground">
              Marca:
            </span>
            <Select
              value={filters.brand_id || 'all'}
              onValueChange={(val) => onApplyFilters({ brand_id: val })}
            >
              <SelectTrigger className="h-8 w-[160px] text-xs">
                <SelectValue placeholder="Todas las marcas" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todas las marcas</SelectItem>
                {brands.map((brand) => (
                  <SelectItem key={brand.id} value={String(brand.id)}>
                    {brand.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {/* Price Range Filter */}
          <PriceRangeForm
            key={`${filters.min_price}_${filters.max_price}`}
            minPrice={filters.min_price || ''}
            maxPrice={filters.max_price || ''}
            onApplyPrice={(min_price, max_price) =>
              onApplyFilters({ min_price, max_price })
            }
          />
        </div>

        {/* Sort Selector */}
        <div className="flex items-center gap-2">
          <ArrowUpDown className="size-3.5 text-muted-foreground" />
          <span className="text-xs font-medium text-muted-foreground">
            Ordenar por:
          </span>
          <Select
            value={filters.sort || 'relevance'}
            onValueChange={(val) => onApplyFilters({ sort: val })}
          >
            <SelectTrigger className="h-8 w-[160px] text-xs">
              <SelectValue placeholder="Ordenar..." />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="relevance">Más relevantes</SelectItem>
              <SelectItem value="price_asc">Menor precio</SelectItem>
              <SelectItem value="price_desc">Mayor precio</SelectItem>
              <SelectItem value="name_asc">Nombre (A – Z)</SelectItem>
              <SelectItem value="name_desc">Nombre (Z – A)</SelectItem>
            </SelectContent>
          </Select>
        </div>
      </div>

      {/* Active Filter Chips Bar */}
      {isFiltered && (
        <div className="flex flex-wrap items-center gap-2 border-t border-border/60 pt-3">
          <span className="flex items-center gap-1 text-xs font-semibold text-muted-foreground">
            <SlidersHorizontal className="size-3" />
            Filtros activos:
          </span>

          {hasSearch && (
            <Badge
              variant="secondary"
              className="gap-1.5 rounded-full pr-1.5 pl-2.5 text-xs font-normal"
            >
              Búsqueda: &ldquo;{filters.search}&rdquo;
              <button
                type="button"
                onClick={() => onApplyFilters({ search: '' })}
                className="rounded-full p-0.5 hover:bg-muted-foreground/20"
                aria-label="Quitar filtro de búsqueda"
              >
                <X className="size-3" />
              </button>
            </Badge>
          )}

          {hasCategory && activeCategory && (
            <Badge
              variant="secondary"
              className="gap-1.5 rounded-full pr-1.5 pl-2.5 text-xs font-normal"
            >
              Categoría: {activeCategory.name}
              <button
                type="button"
                onClick={() => onApplyFilters({ category_id: 'all' })}
                className="rounded-full p-0.5 hover:bg-muted-foreground/20"
                aria-label="Quitar filtro de categoría"
              >
                <X className="size-3" />
              </button>
            </Badge>
          )}

          {hasBrand && activeBrand && (
            <Badge
              variant="secondary"
              className="gap-1.5 rounded-full pr-1.5 pl-2.5 text-xs font-normal"
            >
              Marca: {activeBrand.name}
              <button
                type="button"
                onClick={() => onApplyFilters({ brand_id: 'all' })}
                className="rounded-full p-0.5 hover:bg-muted-foreground/20"
                aria-label="Quitar filtro de marca"
              >
                <X className="size-3" />
              </button>
            </Badge>
          )}

          {(hasMinPrice || hasMaxPrice) && (
            <Badge
              variant="secondary"
              className="gap-1.5 rounded-full pr-1.5 pl-2.5 text-xs font-normal"
            >
              {hasMinPrice && hasMaxPrice
                ? `Precio: $${filters.min_price} – $${filters.max_price}`
                : hasMinPrice
                  ? `Precio mín: $${filters.min_price}`
                  : `Precio máx: $${filters.max_price}`}
              <button
                type="button"
                onClick={() => onApplyFilters({ min_price: '', max_price: '' })}
                className="rounded-full p-0.5 hover:bg-muted-foreground/20"
                aria-label="Quitar filtro de precio"
              >
                <X className="size-3" />
              </button>
            </Badge>
          )}

          {hasCustomSort && (
            <Badge
              variant="secondary"
              className="gap-1.5 rounded-full pr-1.5 pl-2.5 text-xs font-normal"
            >
              Orden: {SORT_LABELS[filters.sort] || filters.sort}
              <button
                type="button"
                onClick={() => onApplyFilters({ sort: 'relevance' })}
                className="rounded-full p-0.5 hover:bg-muted-foreground/20"
                aria-label="Quitar orden personalizado"
              >
                <X className="size-3" />
              </button>
            </Badge>
          )}

          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={onResetFilters}
            className="h-6 gap-1 px-2 text-xs text-muted-foreground hover:text-foreground"
          >
            <FilterX className="size-3" />
            Limpiar filtros
          </Button>
        </div>
      )}

      {/* Results Count Bar */}
      <div className="flex items-center justify-between border-t border-border/40 pt-2 text-xs text-muted-foreground">
        <span>
          {totalArticles === 1
            ? '1 artículo disponible'
            : isFiltered
              ? `${totalArticles} artículos encontrados`
              : `${totalArticles} artículos disponibles`}
        </span>
        {isFiltered && (
          <span className="rounded-md bg-muted px-2 py-0.5 font-medium text-foreground">
            Resultados filtrados
          </span>
        )}
      </div>
    </section>
  );
}
