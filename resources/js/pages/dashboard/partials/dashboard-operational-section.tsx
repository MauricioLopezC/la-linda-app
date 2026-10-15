import { Link } from '@inertiajs/react';
import {
  AlertTriangle,
  ArrowUpRight,
  PackageCheck,
  ShoppingBag,
  TrendingUp,
  Truck,
} from 'lucide-react';
import type { FC } from 'react';
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
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { useCan } from '@/hooks/use-can';
import { formatCurrency } from '@/lib/utils';
import { index as warehouseOrdersRoute } from '@/routes/ecommerce/orders';
import { index as stocksRoute } from '@/routes/inventory/stocks';
import { index as accountStatementRoute } from '@/routes/purchasing/account-statement';
import DashboardSectionPlaceholder from './dashboard-section-placeholder';

type Props = {
  operational: App.Data.Reporting.DashboardOperationalData | null;
};

const title = 'Indicadores operativos';
const description =
  'Artículos más vendidos, compras por proveedor y alertas de stock y despacho.';

const DashboardOperationalSection: FC<Props> = ({ operational }) => {
  const can = useCan();

  if (operational === null) {
    return (
      <DashboardSectionPlaceholder
        title={title}
        description={description}
        story="EPIC-21"
      />
    );
  }

  const {
    top_articles: topArticles,
    stock_alerts: stockAlerts,
    supplier_ranking: supplierRanking,
    pending_web_orders_count: pendingOrdersCount,
  } = operational;

  return (
    <Card className="flex flex-col gap-6">
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        <CardDescription>{description}</CardDescription>
      </CardHeader>

      <CardContent className="flex flex-col gap-6">
        {/* Banner de alerta operativa de despacho online */}
        <div
          className={`flex flex-col items-start justify-between gap-4 rounded-lg border p-4 sm:flex-row sm:items-center ${
            pendingOrdersCount > 0
              ? 'border-amber-500/40 bg-amber-500/10 dark:border-amber-500/30 dark:bg-amber-500/10'
              : 'border-border/60 bg-muted/40'
          }`}
        >
          <div className="flex items-center gap-3">
            <div
              className={`rounded-full p-2.5 ${
                pendingOrdersCount > 0
                  ? 'bg-amber-500/20 text-amber-700 dark:text-amber-400'
                  : 'bg-muted text-muted-foreground'
              }`}
            >
              <Truck className="size-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <span className="font-semibold text-foreground">
                  Despacho online pendiente
                </span>
                {pendingOrdersCount > 0 ? (
                  <Badge variant="destructive" className="font-semibold">
                    {pendingOrdersCount} pendiente
                    {pendingOrdersCount !== 1 ? 's' : ''}
                  </Badge>
                ) : (
                  <Badge variant="secondary">Al día</Badge>
                )}
              </div>
              <p className="text-xs text-muted-foreground sm:text-sm">
                Pedidos web pagados o en preparación pendientes de entrega en
                depósito.
              </p>
            </div>
          </div>

          {can('manage-web-orders') && (
            <Button asChild variant="outline" size="sm" className="shrink-0">
              <Link href={warehouseOrdersRoute()}>
                Ver pedidos por preparar
                <ArrowUpRight className="ml-1 size-4" />
              </Link>
            </Button>
          )}
        </div>

        {/* Paneles de indicadores y rankings */}
        <div className="grid gap-6 lg:grid-cols-3">
          {/* Tarjeta 1: Top artículos más vendidos */}
          <div className="flex flex-col rounded-lg border border-border/70 p-4">
            <div className="mb-4 flex items-center justify-between">
              <div className="flex items-center gap-2">
                <TrendingUp className="size-4 text-primary" />
                <h3 className="text-sm font-semibold text-foreground">
                  Top artículos más vendidos
                </h3>
              </div>
              <Badge variant="outline" className="text-xs font-normal">
                Top {topArticles.length}
              </Badge>
            </div>

            {topArticles.length === 0 ? (
              <div className="flex h-32 flex-col items-center justify-center text-center text-sm text-muted-foreground">
                <ShoppingBag className="mb-1 size-6 opacity-40" />
                <span>Sin ventas confirmadas en el período.</span>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-10">#</TableHead>
                      <TableHead>Artículo</TableHead>
                      <TableHead className="text-right">Cant.</TableHead>
                      <TableHead className="text-right">Total</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {topArticles.map((article, index) => (
                      <TableRow key={article.article_id}>
                        <TableCell className="font-mono text-xs text-muted-foreground">
                          {index + 1}
                        </TableCell>
                        <TableCell className="max-w-[150px] truncate font-medium">
                          {article.description}
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                          {Number(article.quantity).toLocaleString('es-AR', {
                            maximumFractionDigits: 3,
                          })}
                        </TableCell>
                        <TableCell className="text-right font-medium tabular-nums">
                          {formatCurrency(article.total_invoiced)}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            )}
          </div>

          {/* Tarjeta 2: Alertas de stock */}
          <div className="flex flex-col rounded-lg border border-border/70 p-4">
            <div className="mb-4 flex items-center justify-between">
              <div className="flex items-center gap-2">
                <AlertTriangle className="size-4 text-amber-600 dark:text-amber-500" />
                <h3 className="text-sm font-semibold text-foreground">
                  Alertas de stock
                </h3>
              </div>
              {can('manage-stock') && (
                <Button asChild variant="ghost" size="sm" className="h-7 px-2">
                  <Link href={stocksRoute()}>
                    Ver existencias
                    <ArrowUpRight className="ml-1 size-3.5" />
                  </Link>
                </Button>
              )}
            </div>

            {stockAlerts.length === 0 ? (
              <div className="flex h-32 flex-col items-center justify-center text-center text-sm text-muted-foreground">
                <PackageCheck className="mb-1 size-6 opacity-40" />
                <span>Sin artículos sin stock ni en conflicto.</span>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Artículo</TableHead>
                      <TableHead>Depósito</TableHead>
                      <TableHead className="text-right">Stock</TableHead>
                      <TableHead className="text-right">Estado</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {stockAlerts.map((alert) => (
                      <TableRow
                        key={`${alert.article_id}-${alert.warehouse_id}`}
                      >
                        <TableCell className="max-w-[140px] truncate font-medium">
                          {alert.article_description}
                        </TableCell>
                        <TableCell className="text-xs text-muted-foreground">
                          {alert.warehouse_name}
                        </TableCell>
                        <TableCell className="text-right font-mono tabular-nums">
                          {Number(alert.quantity).toLocaleString('es-AR', {
                            maximumFractionDigits: 3,
                          })}
                        </TableCell>
                        <TableCell className="text-right">
                          {alert.has_stock_conflict ? (
                            <Badge
                              variant="destructive"
                              className="px-1.5 py-0 text-[10px]"
                            >
                              Conflicto
                            </Badge>
                          ) : (
                            <Badge
                              variant="secondary"
                              className="px-1.5 py-0 text-[10px]"
                            >
                              Sin stock
                            </Badge>
                          )}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            )}
          </div>

          {/* Tarjeta 3: Compras por proveedor */}
          <div className="flex flex-col rounded-lg border border-border/70 p-4">
            <div className="mb-4 flex items-center justify-between">
              <div className="flex items-center gap-2">
                <ShoppingBag className="size-4 text-primary" />
                <h3 className="text-sm font-semibold text-foreground">
                  Compras por proveedor
                </h3>
              </div>
              {can('manage-purchases') && (
                <Button asChild variant="ghost" size="sm" className="h-7 px-2">
                  <Link href={accountStatementRoute()}>
                    Cuentas corrientes
                    <ArrowUpRight className="ml-1 size-3.5" />
                  </Link>
                </Button>
              )}
            </div>

            {supplierRanking.length === 0 ? (
              <div className="flex h-32 flex-col items-center justify-center text-center text-sm text-muted-foreground">
                <ShoppingBag className="mb-1 size-6 opacity-40" />
                <span>Sin compras a proveedores en el período.</span>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Proveedor</TableHead>
                      <TableHead className="text-right">Compras</TableHead>
                      <TableHead className="text-right">Saldo CC</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {supplierRanking.map((supplier) => (
                      <TableRow key={supplier.supplier_id}>
                        <TableCell className="max-w-[150px] truncate font-medium">
                          {supplier.name}
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                          {formatCurrency(supplier.purchases_total)}
                        </TableCell>
                        <TableCell className="text-right font-medium tabular-nums">
                          {formatCurrency(supplier.outstanding_balance)}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  );
};

export default DashboardOperationalSection;
