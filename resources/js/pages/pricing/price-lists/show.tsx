import { Head, router } from '@inertiajs/react';
import { FilterX, Save, Search, Trash2, Undo2 } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
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
import { dashboard } from '@/routes';
import { index, show } from '@/routes/pricing/price-lists';
import { destroy, store } from '@/routes/pricing/price-lists/items';
import type { BreadcrumbItem } from '@/types';

type PriceList = App.Data.Pricing.PriceListData;
type PriceListArticle = App.Data.Pricing.PriceListArticleData;

/**
 * Shape of a `spatie/laravel-data` `PaginatedDataCollection`, which wraps
 * `Illuminate\Pagination\LengthAwarePaginator::toArray()` verbatim: flat pagination fields
 * alongside `data`, not nested under a `meta` key.
 */
type PriceListArticlePage = {
  data: PriceListArticle[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
  links: { url: string | null; label: string; active: boolean }[];
};

type Props = {
  priceList: PriceList;
  articles: PriceListArticlePage;
  categories: App.Data.Catalog.CategoryData[];
  filters: {
    search?: string | null;
    category_id?: number | null;
    price_status?: string | null;
  };
};

const PRICE_STATUS_OPTIONS = [
  { value: 'all', label: 'Todos los artículos' },
  { value: 'with_price', label: 'Con precio asignado' },
  { value: 'without_price', label: 'Sin precio asignado' },
];

const VALIDITY_BADGE_VARIANT: Record<
  string,
  'default' | 'secondary' | 'outline'
> = {
  vigente: 'default',
  futura: 'outline',
  vencida: 'secondary',
};

/**
 * Compare a draft against the persisted price tolerating the formatting the user types
 * ("180", "180.0", "180.00" are all the same price already on file).
 */
const isSamePrice = (draft: string, stored: string | null): boolean => {
  const trimmed = draft.trim();

  if (trimmed === '') {
    return stored === null;
  }

  if (stored === null) {
    return false;
  }

  return Number(trimmed) === Number(stored);
};

export default function PriceListShow({
  priceList,
  articles,
  categories = [],
  filters,
}: Props) {
  const [searchTerm, setSearchTerm] = useState(filters.search ?? '');
  const [selectedCategory, setSelectedCategory] = useState<string>(
    filters.category_id ? String(filters.category_id) : 'all',
  );
  const [selectedPriceStatus, setSelectedPriceStatus] = useState<string>(
    filters.price_status ?? 'all',
  );
  const [drafts, setDrafts] = useState<Record<number, string>>({});
  const [rowErrors, setRowErrors] = useState<Record<number, string>>({});
  const [isSaving, setIsSaving] = useState(false);
  const [removingArticle, setRemovingArticle] =
    useState<PriceListArticle | null>(null);

  const dirtyArticles = useMemo(
    () =>
      articles.data.filter((article) => {
        const draft = drafts[article.id];

        return draft !== undefined && !isSamePrice(draft, article.price);
      }),
    [articles.data, drafts],
  );

  const applyFilters = useCallback(
    (newFilters: {
      search?: string;
      category_id?: string;
      price_status?: string;
    }) => {
      const current = {
        search: newFilters.search ?? searchTerm,
        category_id: newFilters.category_id ?? selectedCategory,
        price_status: newFilters.price_status ?? selectedPriceStatus,
      };

      const query: Record<string, string> = {};

      if (current.search.trim()) {
        query.search = current.search.trim();
      }

      if (current.category_id !== 'all') {
        query.category_id = current.category_id;
      }

      if (current.price_status !== 'all') {
        query.price_status = current.price_status;
      }

      router.get(
        show.url({ price_list: priceList.id }, { query }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
      );
    },
    [searchTerm, selectedCategory, selectedPriceStatus, priceList.id],
  );

  const clearFilters = () => {
    setSearchTerm('');
    setSelectedCategory('all');
    setSelectedPriceStatus('all');

    router.get(
      show.url({ price_list: priceList.id }),
      {},
      { preserveState: true, preserveScroll: true, replace: true },
    );
  };

  const goToPage = (url: string | null) => {
    if (!url) {
      return;
    }

    router.get(
      url,
      {},
      { preserveState: true, preserveScroll: true, replace: true },
    );
  };

  const discardDrafts = () => {
    setDrafts({});
    setRowErrors({});
  };

  const savePrices = () => {
    if (dirtyArticles.length === 0) {
      return;
    }

    const emptied = dirtyArticles.filter(
      (article) => drafts[article.id]?.trim() === '',
    );

    if (emptied.length > 0) {
      toast.error(
        'Para dejar un artículo sin precio usá el botón de quitar precio, no borres el campo.',
      );

      return;
    }

    const prices = dirtyArticles.map((article) => ({
      article_id: article.id,
      price: drafts[article.id].trim(),
    }));

    setIsSaving(true);

    router.post(
      store.url({ price_list: priceList.id }),
      { prices },
      {
        preserveScroll: true,
        onSuccess: () => {
          discardDrafts();
          toast.success(
            prices.length === 1
              ? 'Precio guardado correctamente'
              : `Se guardaron ${prices.length} precios correctamente`,
          );
        },
        onError: (errors) => {
          // Server errors come back keyed by payload position (`prices.3.price`), so map each
          // one back to the article that occupied that position in this submission.
          const mapped: Record<number, string> = {};

          Object.entries(errors).forEach(([key, message]) => {
            const position = Number(key.split('.')[1]);
            const article = dirtyArticles[position];

            if (article && typeof message === 'string') {
              mapped[article.id] = message;
            }
          });

          setRowErrors(mapped);
          toast.error('Revisá los precios marcados en rojo');
        },
        onFinish: () => setIsSaving(false),
      },
    );
  };

  const confirmRemove = () => {
    if (!removingArticle) {
      return;
    }

    const article = removingArticle;

    router.delete(
      destroy.url({ price_list: priceList.id, article: article.id }),
      {
        preserveScroll: true,
        onSuccess: () => {
          setDrafts((current) => {
            const next = { ...current };
            delete next[article.id];

            return next;
          });
          toast.success(`Se quitó el precio de "${article.description}"`);
        },
        onError: () => toast.error('No se pudo quitar el precio'),
        onFinish: () => setRemovingArticle(null),
      },
    );
  };

  const hasActiveFilters =
    searchTerm.trim() !== '' ||
    selectedCategory !== 'all' ||
    selectedPriceStatus !== 'all';

  return (
    <>
      <Head title={`Precios · ${priceList.name}`} />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <Heading
            title={priceList.name}
            description={
              priceList.description ??
              'Cargá o corregí el precio de venta de cada artículo en esta lista.'
            }
          />
          <Button variant="outline" onClick={() => router.get(index.url())}>
            Volver a las listas
          </Button>
        </div>

        <div className="flex flex-wrap items-center gap-2 rounded-xl border border-sidebar-border bg-card p-4 shadow-xs">
          <Badge variant="outline">{priceList.scope_label}</Badge>
          {priceList.channel_label && (
            <Badge variant="outline">Canal: {priceList.channel_label}</Badge>
          )}
          <Badge variant={priceList.is_active ? 'default' : 'secondary'}>
            {priceList.is_active ? 'Activa' : 'Inactiva'}
          </Badge>
          <Badge
            variant={
              VALIDITY_BADGE_VARIANT[priceList.validity_status] ?? 'outline'
            }
          >
            {priceList.validity_status_label}
          </Badge>
          <span className="text-sm text-muted-foreground">
            Vigencia: {priceList.valid_from}
            {priceList.valid_to ? ` — ${priceList.valid_to}` : ' — sin fin'}
          </span>
          <span className="ml-auto text-sm font-medium text-foreground">
            {priceList.articles_with_price_count} artículos con precio
          </span>
        </div>

        <div className="flex flex-col gap-3 rounded-xl border border-sidebar-border bg-card p-4 shadow-xs">
          <form
            onSubmit={(event) => {
              event.preventDefault();
              applyFilters({ search: searchTerm });
            }}
            className="grid grid-cols-1 gap-3 md:grid-cols-3"
          >
            <div className="relative min-w-0">
              <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder="Buscar por código, descripción o código de barras..."
                value={searchTerm}
                onChange={(event) => setSearchTerm(event.target.value)}
                onBlur={() => applyFilters({ search: searchTerm })}
                className="pl-9"
              />
            </div>

            <div className="min-w-0">
              <Select
                value={selectedCategory}
                onValueChange={(value) => {
                  setSelectedCategory(value);
                  applyFilters({ category_id: value });
                }}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Todas las categorías" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">Todas las categorías</SelectItem>
                  {categories.map((category) => (
                    <SelectItem key={category.id} value={String(category.id)}>
                      {category.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="min-w-0">
              <Select
                value={selectedPriceStatus}
                onValueChange={(value) => {
                  setSelectedPriceStatus(value);
                  applyFilters({ price_status: value });
                }}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Estado del precio" />
                </SelectTrigger>
                <SelectContent>
                  {PRICE_STATUS_OPTIONS.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
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
                onClick={clearFilters}
                className="h-8 text-xs text-muted-foreground hover:text-foreground"
              >
                <FilterX className="mr-1.5 size-3.5" />
                Limpiar filtros
              </Button>
            </div>
          )}
        </div>

        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-sm text-muted-foreground">
            {dirtyArticles.length === 0
              ? 'Escribí los precios que quieras cargar o corregir y guardalos todos juntos.'
              : `${dirtyArticles.length} precio(s) sin guardar.`}
          </p>
          <div className="flex gap-2">
            <Button
              variant="outline"
              onClick={discardDrafts}
              disabled={dirtyArticles.length === 0 || isSaving}
            >
              <Undo2 className="mr-1.5 size-4" />
              Descartar cambios
            </Button>
            <Button
              onClick={savePrices}
              disabled={dirtyArticles.length === 0 || isSaving}
            >
              <Save className="mr-1.5 size-4" />
              Guardar precios
              {dirtyArticles.length > 0 ? ` (${dirtyArticles.length})` : ''}
            </Button>
          </div>
        </div>

        <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Código</TableHead>
                <TableHead>Descripción</TableHead>
                <TableHead>Categoría</TableHead>
                <TableHead>Unidad</TableHead>
                <TableHead className="w-44">Precio de venta</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {articles.data.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={6}
                    className="py-12 text-center text-muted-foreground"
                  >
                    No se encontraron artículos con los filtros aplicados.
                  </TableCell>
                </TableRow>
              ) : (
                articles.data.map((article) => {
                  const draft = drafts[article.id];
                  const value = draft ?? article.price ?? '';
                  const error = rowErrors[article.id];

                  return (
                    <TableRow key={article.id}>
                      <TableCell className="font-mono text-xs">
                        {article.internal_code}
                      </TableCell>
                      <TableCell className="font-medium">
                        <div className="flex flex-col gap-1">
                          <span>{article.description}</span>
                          {!article.is_active && (
                            <Badge
                              variant="secondary"
                              className="w-fit text-xs"
                            >
                              Artículo inactivo
                            </Badge>
                          )}
                        </div>
                      </TableCell>
                      <TableCell>{article.category_name}</TableCell>
                      <TableCell>{article.unit_of_measure_name}</TableCell>
                      <TableCell>
                        <Input
                          type="number"
                          step="0.01"
                          min="0.01"
                          inputMode="decimal"
                          placeholder="Sin precio"
                          value={value}
                          disabled={!article.is_active}
                          aria-invalid={error ? true : undefined}
                          aria-label={`Precio de ${article.description}`}
                          onChange={(event) =>
                            setDrafts((current) => ({
                              ...current,
                              [article.id]: event.target.value,
                            }))
                          }
                        />
                        {error && (
                          <p className="mt-1 text-xs text-destructive">
                            {error}
                          </p>
                        )}
                        {!article.is_active && (
                          <p className="mt-1 text-xs text-muted-foreground">
                            Precio histórico: el artículo está inactivo y solo
                            se puede quitar.
                          </p>
                        )}
                      </TableCell>
                      <TableCell className="text-right">
                        <Button
                          variant="ghost"
                          size="icon"
                          disabled={!article.has_price}
                          onClick={() => setRemovingArticle(article)}
                          aria-label={`Quitar precio de ${article.description}`}
                          className="text-destructive hover:text-destructive"
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

        {articles.last_page > 1 && (
          <div className="flex flex-col items-center gap-3 sm:flex-row sm:justify-between">
            <p className="text-sm text-muted-foreground">
              Mostrando {articles.from ?? 0}–{articles.to ?? 0} de{' '}
              {articles.total} artículos
            </p>
            <Pagination className="mx-0 w-auto">
              <PaginationContent>
                <PaginationItem>
                  <PaginationPrevious
                    href={articles.links[0]?.url ?? '#'}
                    aria-disabled={!articles.links[0]?.url}
                    className={
                      !articles.links[0]?.url
                        ? 'pointer-events-none opacity-50'
                        : undefined
                    }
                    onClick={(event) => {
                      event.preventDefault();
                      goToPage(articles.links[0]?.url ?? null);
                    }}
                  />
                </PaginationItem>

                {articles.links.slice(1, -1).map((link, position) =>
                  link.url === null ? (
                    <PaginationItem key={`ellipsis-${position}`}>
                      <PaginationEllipsis />
                    </PaginationItem>
                  ) : (
                    <PaginationItem key={link.label}>
                      <PaginationLink
                        href={link.url}
                        isActive={link.active}
                        onClick={(event) => {
                          event.preventDefault();
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
                    href={articles.links[articles.links.length - 1]?.url ?? '#'}
                    aria-disabled={
                      !articles.links[articles.links.length - 1]?.url
                    }
                    className={
                      !articles.links[articles.links.length - 1]?.url
                        ? 'pointer-events-none opacity-50'
                        : undefined
                    }
                    onClick={(event) => {
                      event.preventDefault();
                      goToPage(
                        articles.links[articles.links.length - 1]?.url ?? null,
                      );
                    }}
                  />
                </PaginationItem>
              </PaginationContent>
            </Pagination>
          </div>
        )}
      </div>

      <Dialog
        open={removingArticle !== null}
        onOpenChange={(open) => !open && setRemovingArticle(null)}
      >
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Quitar precio de la lista</DialogTitle>
            <DialogDescription>
              ¿Querés quitar el precio de &quot;
              {removingArticle?.description}&quot; en la lista {priceList.name}?
              El artículo vuelve a quedar sin precio en esta lista.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => setRemovingArticle(null)}
            >
              Cancelar
            </Button>
            <Button type="button" variant="destructive" onClick={confirmRemove}>
              Quitar precio
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

PriceListShow.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Precios', href: '#' },
    { title: 'Listas de Precios', href: index() },
    { title: 'Precios de la lista', href: '#' },
  ] satisfies BreadcrumbItem[],
};
