import { Head, Link, router, usePage } from '@inertiajs/react';
import {
  ArrowRight,
  Barcode,
  CheckCircle2,
  FilterX,
  Minus,
  Package,
  PackageSearch,
  Plus,
  Search,
  ShieldCheck,
  ShoppingCart,
  Tag,
  UserCheck,
  X,
} from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';
import TablePagination from '@/components/table-pagination';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { home } from '@/routes/tienda';
import type { BreadcrumbItem } from '@/types';

type Article = App.Data.Ecommerce.OnlineCatalogArticleData;
type Category = App.Data.Catalog.CategoryData;

type PaginationProps = {
  data: Article[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

type Props = {
  articles: PaginationProps;
  categories: Category[];
  filters: {
    search: string;
    category_id: string;
  };
};

export default function StoreHome({ articles, categories, filters }: Props) {
  const { auth, flash } = usePage().props;
  const user = auth?.user;
  const flashSuccess =
    typeof flash?.success === 'string' ? flash.success : null;

  const [searchQuery, setSearchQuery] = useState(filters.search || '');
  const selectedCategoryId = filters.category_id || 'all';

  // Modal preview state
  const [previewArticle, setPreviewArticle] = useState<Article | null>(null);

  // Quantities state per article
  const [quantities, setQuantities] = useState<Record<number, number>>({});

  const getArticleQty = (
    articleId: number,
    allowsDecimals: boolean,
  ): number => {
    return quantities[articleId] ?? (allowsDecimals ? 1 : 1);
  };

  const updateQuantity = (
    articleId: number,
    delta: number,
    allowsDecimals: boolean,
  ) => {
    setQuantities((prev) => {
      const current = prev[articleId] ?? 1;
      const step = allowsDecimals ? 0.5 : 1;
      const min = allowsDecimals ? 0.5 : 1;
      const updated = Math.max(
        min,
        Math.round((current + delta * step) * 10) / 10,
      );

      return { ...prev, [articleId]: updated };
    });
  };

  const handleAddToCart = (article: Article, explicitQty?: number) => {
    const qty =
      explicitQty ?? getArticleQty(article.id, article.allows_decimals);

    if (!user) {
      toast.info('Iniciá sesión para armar tu carrito de compras', {
        action: {
          label: 'Iniciar sesión',
          onClick: () => router.visit('/login'),
        },
      });

      return;
    }

    toast.success(
      `Agregaste ${qty} ${article.unit_of_measure_abbreviation || 'u.'} de "${article.description}" al carrito`,
    );
  };

  const applyFilters = (newCategoryId?: string, newSearch?: string) => {
    const effectiveCategory =
      newCategoryId !== undefined ? newCategoryId : selectedCategoryId;
    const effectiveSearch = newSearch !== undefined ? newSearch : searchQuery;

    router.get(
      home.url({
        query: {
          search: effectiveSearch.trim() || undefined,
          category_id:
            effectiveCategory !== 'all' ? effectiveCategory : undefined,
        },
      }),
      {},
      { preserveState: true, preserveScroll: true },
    );
  };

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    applyFilters(undefined, searchQuery);
  };

  const handleCategorySelect = (catId: string) => {
    applyFilters(catId, undefined);
  };

  const resetFilters = () => {
    setSearchQuery('');
    router.get(home.url(), {}, { preserveState: true, preserveScroll: true });
  };

  const changePage = (page: number) => {
    router.get(
      home.url({
        query: {
          page,
          search: filters.search || undefined,
          category_id:
            filters.category_id !== 'all' ? filters.category_id : undefined,
        },
      }),
      {},
      { preserveState: true, preserveScroll: true },
    );
  };

  const isFiltered =
    Boolean(filters.search) ||
    (Boolean(filters.category_id) && filters.category_id !== 'all');

  return (
    <>
      <Head title="Tienda Online | Supermercados La Linda" />

      <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
        {flashSuccess && (
          <Alert className="border-green-600/30 bg-green-500/10 text-green-700 dark:border-green-500/30 dark:text-green-400">
            <CheckCircle2 className="size-4 text-green-600 dark:text-green-400" />
            <AlertTitle>Operación exitosa</AlertTitle>
            <AlertDescription>{flashSuccess}</AlertDescription>
          </Alert>
        )}

        {/* Hero Banner */}
        <section className="relative overflow-hidden rounded-2xl border border-border bg-gradient-to-br from-primary/15 via-primary/5 to-background p-6 shadow-xs md:p-8">
          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div className="max-w-2xl space-y-2">
              <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/20 px-3 py-1 text-xs font-semibold text-primary">
                <ShieldCheck className="size-3.5" /> Supermercados La Linda ·
                Tienda Oficial
              </span>
              <h1 className="text-2xl font-extrabold tracking-tight text-foreground sm:text-3xl">
                Catálogo de Productos
              </h1>
              <p className="text-sm text-muted-foreground sm:text-base">
                Comprá desde tu casa con los mejores precios, promociones
                vigentes y calidad garantizada.
              </p>
            </div>
            <div className="flex flex-wrap items-center gap-2 pt-1">
              {user ? (
                <Button asChild size="default" className="gap-2">
                  <Link href="/tienda/mi-cuenta">
                    <UserCheck className="size-4" />
                    Mi cuenta ({user.name})
                  </Link>
                </Button>
              ) : (
                <>
                  <Button asChild size="default" className="gap-2">
                    <Link href="/register">
                      Crear mi cuenta
                      <ArrowRight className="size-4" />
                    </Link>
                  </Button>
                  <Button asChild variant="outline" size="default">
                    <Link href="/login">Iniciar sesión</Link>
                  </Button>
                </>
              )}
            </div>
          </div>
        </section>

        {/* Search & Category Filter Section */}
        <section className="flex flex-col gap-4 rounded-xl border border-border bg-card p-4 shadow-xs">
          {/* Search Bar Form */}
          <form
            onSubmit={handleSearchSubmit}
            className="flex flex-col gap-2 sm:flex-row sm:items-center"
          >
            <div className="relative flex-1">
              <Search className="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                type="text"
                placeholder="Buscar artículos por descripción (ej. leche, arroz, fideos)..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pr-9 pl-10"
              />
              {searchQuery && (
                <button
                  type="button"
                  onClick={() => setSearchQuery('')}
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
                  onClick={resetFilters}
                  className="gap-2"
                >
                  <FilterX className="size-4" />
                  Limpiar filtros
                </Button>
              )}
            </div>
          </form>

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
                  variant={selectedCategoryId === 'all' ? 'default' : 'outline'}
                  onClick={() => handleCategorySelect('all')}
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
                      selectedCategoryId === String(category.id)
                        ? 'default'
                        : 'outline'
                    }
                    onClick={() => handleCategorySelect(String(category.id))}
                    className="h-8 rounded-full text-xs font-medium"
                  >
                    {category.name}
                  </Button>
                ))}
              </div>
            </div>
          )}
        </section>

        {/* Results Info & Count */}
        <div className="flex items-center justify-between px-1 text-sm text-muted-foreground">
          <span>
            {articles.total === 1
              ? '1 artículo disponible'
              : `${articles.total} artículos disponibles`}
          </span>
          {isFiltered && (
            <span className="rounded-md bg-muted px-2.5 py-1 text-xs font-medium text-foreground">
              Resultados filtrados
            </span>
          )}
        </div>

        {/* Catalog Grid */}
        {articles.data.length > 0 ? (
          <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            {articles.data.map((article) => {
              const qty = getArticleQty(article.id, article.allows_decimals);

              return (
                <div
                  key={article.id}
                  className="group flex flex-col overflow-hidden rounded-xl border border-border bg-card shadow-2xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
                >
                  {/* Image Placeholder Area */}
                  <div className="relative flex aspect-square w-full items-center justify-center border-b border-border bg-muted/20 p-6 select-none">
                    <div className="flex size-20 items-center justify-center rounded-2xl bg-muted/50 text-muted-foreground/60 shadow-inner transition-transform duration-200 group-hover:scale-105">
                      <Package className="size-10" />
                    </div>

                    {/* Category Badge */}
                    <Badge
                      variant="secondary"
                      className="absolute top-3 left-3 border border-border/60 bg-background/90 text-xs font-normal backdrop-blur-xs"
                    >
                      {article.category_name}
                    </Badge>

                    {/* Particular Price Badge */}
                    {article.is_particular_price && (
                      <Badge className="absolute top-9 left-3 gap-1 bg-primary text-[10px] font-medium text-primary-foreground shadow-xs">
                        <Tag className="size-2.5" />
                        Precio preferencial
                      </Badge>
                    )}

                    {/* Quick View Button (Magnifying Glass) */}
                    <Button
                      type="button"
                      variant="secondary"
                      size="icon"
                      onClick={() => setPreviewArticle(article)}
                      title="Ver información del producto"
                      aria-label="Ver detalles del producto"
                      className="absolute top-3 right-3 size-8 rounded-full border border-border/60 bg-background/90 text-muted-foreground shadow-xs backdrop-blur-xs hover:bg-background hover:text-foreground"
                    >
                      <Search className="size-4" />
                    </Button>

                    <span className="absolute bottom-2 left-3 text-[10px] tracking-wider text-muted-foreground/50">
                      Imagen ilustrativa
                    </span>
                  </div>

                  {/* Article Info Body */}
                  <div className="flex flex-1 flex-col justify-between gap-3 p-4">
                    <div className="space-y-1">
                      {article.brand_name ? (
                        <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                          {article.brand_name}
                        </span>
                      ) : (
                        <span className="text-xs text-transparent select-none">
                          -
                        </span>
                      )}
                      <h3
                        className="line-clamp-2 min-h-[2.75rem] cursor-pointer text-base leading-snug font-bold text-foreground transition-colors hover:text-primary"
                        title={article.description}
                        onClick={() => setPreviewArticle(article)}
                      >
                        {article.description}
                      </h3>
                      <p className="text-xs text-muted-foreground">
                        Venta por {article.unit_of_measure_name.toLowerCase()}
                      </p>
                    </div>

                    {/* Price and Add to Cart Section */}
                    <div className="space-y-3 border-t border-border/50 pt-2.5">
                      <div>
                        <div className="flex items-baseline justify-between gap-2">
                          <span className="text-2xl font-extrabold tracking-tight text-foreground">
                            {article.formatted_price}
                          </span>
                          <span className="text-xs font-medium text-muted-foreground">
                            /{article.unit_of_measure_abbreviation || 'u'}
                          </span>
                        </div>
                        {article.is_particular_price && (
                          <p className="mt-0.5 flex items-center gap-1 text-[11px] font-medium text-primary">
                            <Tag className="size-3" />
                            Precio de tu lista particular
                          </p>
                        )}
                      </div>

                      {/* Quantity Stepper and Add Button */}
                      <div className="flex items-center gap-2">
                        <div className="flex items-center rounded-lg border border-border bg-muted/30 p-0.5">
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-7 rounded-md text-muted-foreground hover:text-foreground"
                            onClick={() =>
                              updateQuantity(
                                article.id,
                                -1,
                                article.allows_decimals,
                              )
                            }
                            disabled={
                              qty <= (article.allows_decimals ? 0.5 : 1)
                            }
                            title="Disminuir cantidad"
                          >
                            <Minus className="size-3" />
                          </Button>
                          <span className="w-8 text-center text-xs font-semibold tabular-nums">
                            {qty}
                          </span>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-7 rounded-md text-muted-foreground hover:text-foreground"
                            onClick={() =>
                              updateQuantity(
                                article.id,
                                1,
                                article.allows_decimals,
                              )
                            }
                            title="Aumentar cantidad"
                          >
                            <Plus className="size-3" />
                          </Button>
                        </div>

                        <Button
                          type="button"
                          className="h-8 flex-1 gap-1.5 text-xs font-semibold"
                          onClick={() => handleAddToCart(article, qty)}
                        >
                          <ShoppingCart className="size-3.5" />
                          Añadir
                        </Button>
                      </div>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        ) : (
          /* Empty State */
          <div className="my-6 flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card/40 p-12 text-center">
            <div className="mb-4 flex size-16 items-center justify-center rounded-full bg-muted/60 text-muted-foreground">
              <PackageSearch className="size-8" />
            </div>
            <h3 className="text-lg font-bold text-foreground">
              No encontramos productos
            </h3>
            <p className="mt-1.5 max-w-sm text-sm text-muted-foreground">
              No hay artículos que coincidan con tu búsqueda o en la categoría
              seleccionada.
            </p>
            {isFiltered && (
              <Button
                variant="outline"
                onClick={resetFilters}
                className="mt-5 gap-2"
              >
                <FilterX className="size-4" />
                Ver todo el catálogo
              </Button>
            )}
          </div>
        )}

        {/* Pagination */}
        <TablePagination
          currentPage={articles.current_page}
          totalPages={articles.last_page}
          totalItems={articles.total}
          pageSize={articles.per_page}
          onPageChange={changePage}
          entityName="artículos"
          className="mt-4"
        />
      </div>

      {/* Product Detail Modal */}
      <Dialog
        open={Boolean(previewArticle)}
        onOpenChange={(open) => !open && setPreviewArticle(null)}
      >
        {previewArticle && (
          <DialogContent className="overflow-hidden p-0 sm:max-w-xl md:max-w-2xl">
            <div className="grid grid-cols-1 md:grid-cols-2">
              {/* Modal Left: Product Graphic */}
              <div className="relative flex aspect-square items-center justify-center border-b border-border bg-muted/20 p-8 md:border-r md:border-b-0">
                <div className="flex size-32 items-center justify-center rounded-3xl bg-muted/50 text-muted-foreground/60 shadow-inner">
                  <Package className="size-16" />
                </div>

                <Badge
                  variant="secondary"
                  className="absolute top-4 left-4 border border-border/60 bg-background/90 text-xs backdrop-blur-xs"
                >
                  {previewArticle.category_name}
                </Badge>

                {previewArticle.is_particular_price && (
                  <Badge className="absolute top-4 right-4 gap-1 bg-primary text-xs font-medium text-primary-foreground">
                    <Tag className="size-3" />
                    Precio preferencial
                  </Badge>
                )}

                <span className="absolute bottom-3 left-4 text-xs text-muted-foreground/60">
                  Imagen ilustrativa
                </span>
              </div>

              {/* Modal Right: Full Information */}
              <div className="flex flex-col justify-between p-6">
                <DialogHeader className="space-y-1.5 text-left">
                  {previewArticle.brand_name && (
                    <span className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                      {previewArticle.brand_name}
                    </span>
                  )}
                  <DialogTitle className="text-xl leading-snug font-bold text-foreground">
                    {previewArticle.description}
                  </DialogTitle>
                  <DialogDescription className="text-xs text-muted-foreground">
                    Unidad de venta: {previewArticle.unit_of_measure_name} (
                    {previewArticle.unit_of_measure_abbreviation || 'u'})
                  </DialogDescription>
                </DialogHeader>

                {/* Technical Details Badges */}
                <div className="my-4 space-y-2 rounded-lg border border-border bg-muted/20 p-3 text-xs text-muted-foreground">
                  <div className="flex items-center justify-between">
                    <span className="font-medium text-foreground">
                      Código interno:
                    </span>
                    <span className="font-mono">
                      {previewArticle.internal_code}
                    </span>
                  </div>
                  {previewArticle.barcode && (
                    <div className="flex items-center justify-between">
                      <span className="flex items-center gap-1 font-medium text-foreground">
                        <Barcode className="size-3.5" /> Código de barras:
                      </span>
                      <span className="font-mono">
                        {previewArticle.barcode}
                      </span>
                    </div>
                  )}
                  <div className="flex items-center justify-between">
                    <span className="font-medium text-foreground">
                      Lista aplicada:
                    </span>
                    <span>{previewArticle.price_list_name}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="font-medium text-foreground">
                      Admite decimales:
                    </span>
                    <span>
                      {previewArticle.allows_decimals
                        ? 'Sí (por peso)'
                        : 'No (entero)'}
                    </span>
                  </div>
                </div>

                {/* Price and Cart Actions */}
                <div className="space-y-4 border-t border-border/60 pt-4">
                  <div>
                    <div className="flex items-baseline justify-between">
                      <span className="text-3xl font-extrabold tracking-tight text-foreground">
                        {previewArticle.formatted_price}
                      </span>
                      <span className="text-sm font-medium text-muted-foreground">
                        /{previewArticle.unit_of_measure_abbreviation || 'u'}
                      </span>
                    </div>
                    {previewArticle.is_particular_price && (
                      <p className="mt-1 flex items-center gap-1.5 text-xs font-medium text-primary">
                        <Tag className="size-3.5" />
                        Precio preferencial asignado a tu cuenta
                      </p>
                    )}
                  </div>

                  {/* Quantity and Action Button */}
                  <div className="flex items-center gap-3">
                    <div className="flex items-center rounded-lg border border-border bg-muted/30 p-1">
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8 rounded-md"
                        onClick={() =>
                          updateQuantity(
                            previewArticle.id,
                            -1,
                            previewArticle.allows_decimals,
                          )
                        }
                        disabled={
                          getArticleQty(
                            previewArticle.id,
                            previewArticle.allows_decimals,
                          ) <= (previewArticle.allows_decimals ? 0.5 : 1)
                        }
                      >
                        <Minus className="size-3.5" />
                      </Button>
                      <span className="w-10 text-center text-sm font-semibold tabular-nums">
                        {getArticleQty(
                          previewArticle.id,
                          previewArticle.allows_decimals,
                        )}
                      </span>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8 rounded-md"
                        onClick={() =>
                          updateQuantity(
                            previewArticle.id,
                            1,
                            previewArticle.allows_decimals,
                          )
                        }
                      >
                        <Plus className="size-3.5" />
                      </Button>
                    </div>

                    <Button
                      type="button"
                      size="default"
                      className="flex-1 gap-2"
                      onClick={() => {
                        handleAddToCart(previewArticle);
                        setPreviewArticle(null);
                      }}
                    >
                      <ShoppingCart className="size-4" />
                      Añadir al carrito
                    </Button>
                  </div>
                </div>
              </div>
            </div>
          </DialogContent>
        )}
      </Dialog>
    </>
  );
}

StoreHome.layout = {
  breadcrumbs: [
    {
      title: 'Tienda Online',
      href: '/tienda',
    },
  ] satisfies BreadcrumbItem[],
};
