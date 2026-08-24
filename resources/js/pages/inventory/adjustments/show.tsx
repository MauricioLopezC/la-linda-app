import { Head } from '@inertiajs/react';
import {
  ArrowLeft,
  Calendar,
  FileText,
  Printer,
  Plus,
  ShieldCheck,
  User,
  Warehouse,
} from 'lucide-react';
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
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface MovementItemDetail {
  id: number;
  article_id: number;
  article_description: string;
  article_internal_code: string;
  article_barcode: string | null;
  category_name: string;
  brand_name: string | null;
  unit_of_measure_name: string;
  unit_of_measure_code: string;
  quantity: string;
  system_quantity: string | null;
  final_quantity: string;
}

interface MovementDetail {
  id: number;
  type_name: string;
  type_code: string;
  warehouse_id: number;
  warehouse_name: string;
  branch_name: string;
  reason_id: number | null;
  reason_name: string | null;
  notes: string | null;
  user_id: number;
  user_name: string;
  created_at: string;
  created_at_formatted: string;
  items: MovementItemDetail[];
}

interface Props {
  movement: MovementDetail;
}

export default function ShowStockAdjustment({ movement }: Props) {
  const breadcrumbs: BreadcrumbItem[] = [
    {
      title: 'Inventario',
      href: '/inventory/stocks',
    },
    {
      title: 'Ajustes',
      href: '/inventory/adjustments/create',
    },
    {
      title: `Movimiento #${movement.id}`,
      href: `/inventory/adjustments/${movement.id}`,
    },
  ];

  const handlePrint = () => {
    window.print();
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={`Comprobante de Ajuste #${movement.id}`} />

      <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6 print:max-w-full print:p-0">
        {/* Action buttons (hidden when printing) */}
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between print:hidden">
          <div className="flex items-center gap-2">
            <Button variant="outline" asChild size="sm" className="gap-1.5">
              <a href="/inventory/stocks">
                <ArrowLeft className="h-4 w-4" />
                Volver a Existencias
              </a>
            </Button>
          </div>

          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              size="sm"
              onClick={handlePrint}
              className="gap-1.5"
            >
              <Printer className="h-4 w-4" />
              Imprimir Comprobante
            </Button>
            <Button asChild size="sm" className="gap-1.5">
              <a href="/inventory/adjustments/create">
                <Plus className="h-4 w-4" />
                Nuevo Ajuste
              </a>
            </Button>
          </div>
        </div>

        {/* Official Voucher Card */}
        <Card className="border shadow-sm print:border-none print:shadow-none">
          <CardHeader className="border-b bg-muted/30 pb-6 print:bg-transparent">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <div className="flex items-center gap-2">
                  <span className="text-xs font-bold tracking-wider text-muted-foreground uppercase">
                    Supermercados La Linda S.A.
                  </span>
                  <Badge
                    variant="outline"
                    className="gap-1 border-emerald-500/30 bg-emerald-500/10 text-emerald-600"
                  >
                    <ShieldCheck className="h-3.5 w-3.5" />
                    Movimiento Confirmado (Inmutable)
                  </Badge>
                </div>
                <CardTitle className="mt-1 text-2xl font-bold tracking-tight">
                  Comprobante de Ajuste de Stock
                </CardTitle>
                <CardDescription className="text-sm">
                  Documento respaldatorio de recuento físico e inventario
                  oficial
                </CardDescription>
              </div>

              <div className="text-left sm:text-right">
                <div className="text-xs text-muted-foreground">
                  Número de Movimiento
                </div>
                <div className="font-mono text-xl font-bold tracking-tight text-primary">
                  #{String(movement.id).padStart(6, '0')}
                </div>
                <div className="mt-0.5 text-xs text-muted-foreground">
                  {movement.created_at_formatted}
                </div>
              </div>
            </div>
          </CardHeader>

          <CardContent className="space-y-6 pt-6">
            {/* Header Metadata Grid */}
            <div className="grid grid-cols-1 gap-4 rounded-lg border bg-muted/40 p-4 sm:grid-cols-2 md:grid-cols-4 print:bg-transparent">
              <div className="space-y-1">
                <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                  <Warehouse className="h-3.5 w-3.5" />
                  <span>Depósito</span>
                </div>
                <div className="text-sm font-semibold">
                  {movement.warehouse_name}
                </div>
                <div className="text-xs text-muted-foreground">
                  {movement.branch_name}
                </div>
              </div>

              <div className="space-y-1">
                <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                  <FileText className="h-3.5 w-3.5" />
                  <span>Motivo Documentado</span>
                </div>
                <div className="text-sm font-semibold">
                  {movement.reason_name ?? 'Sin motivo'}
                </div>
                <div className="text-xs text-muted-foreground">
                  {movement.type_name}
                </div>
              </div>

              <div className="space-y-1">
                <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                  <User className="h-3.5 w-3.5" />
                  <span>Usuario Responsable</span>
                </div>
                <div className="text-sm font-semibold">
                  {movement.user_name}
                </div>
                <div className="text-xs text-muted-foreground">
                  ID: #{movement.user_id}
                </div>
              </div>

              <div className="space-y-1">
                <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                  <Calendar className="h-3.5 w-3.5" />
                  <span>Fecha de Registro</span>
                </div>
                <div className="text-sm font-semibold">
                  {movement.created_at_formatted.split(' ')[0]}
                </div>
                <div className="text-xs text-muted-foreground">
                  {movement.created_at_formatted.split(' ')[1]} hs
                </div>
              </div>
            </div>

            {/* Observations if any */}
            {movement.notes && (
              <div className="border-l-2 border-primary bg-muted/20 py-1 pl-3 text-xs">
                <span className="font-semibold text-foreground">
                  Observaciones registradas:{' '}
                </span>
                <span className="text-muted-foreground">{movement.notes}</span>
              </div>
            )}

            {/* Detail Table */}
            <div className="overflow-hidden rounded-md border">
              <Table>
                <TableHeader className="bg-muted/50">
                  <TableRow>
                    <TableHead className="w-[80px]">Código</TableHead>
                    <TableHead>Artículo / Categoría</TableHead>
                    <TableHead className="w-[100px]">Unidad</TableHead>
                    <TableHead className="w-[130px] text-right">
                      Stock Anterior
                    </TableHead>
                    <TableHead className="w-[130px] text-right">
                      Stock Resultante
                    </TableHead>
                    <TableHead className="w-[150px] text-right">
                      Ajuste Aplicado
                    </TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {movement.items.map((item) => {
                    const delta = parseFloat(item.quantity);
                    const sysQty =
                      item.system_quantity !== null
                        ? parseFloat(item.system_quantity)
                        : 0;
                    const finalQty = parseFloat(item.final_quantity);

                    return (
                      <TableRow key={item.id}>
                        <TableCell className="font-mono text-xs font-semibold">
                          {item.article_internal_code}
                        </TableCell>
                        <TableCell>
                          <div className="text-sm font-medium">
                            {item.article_description}
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
                          {sysQty.toFixed(3)}
                        </TableCell>
                        <TableCell className="text-right font-mono text-sm font-semibold">
                          {finalQty.toFixed(3)}
                        </TableCell>
                        <TableCell className="text-right font-mono text-sm">
                          {delta > 0.0001 ? (
                            <span className="font-semibold text-emerald-600">
                              +{delta.toFixed(3)} (Sobrante)
                            </span>
                          ) : delta < -0.0001 ? (
                            <span className="font-semibold text-rose-600">
                              {delta.toFixed(3)} (Faltante)
                            </span>
                          ) : (
                            <span className="text-muted-foreground">
                              0.000 (Sin cambio)
                            </span>
                          )}
                        </TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
              </Table>
            </div>

            {/* Signature Area (Visible when printing) */}
            <div className="mt-12 hidden grid-cols-2 gap-12 border-t pt-16 text-center text-xs print:grid">
              <div>
                <div className="mx-auto w-48 border-t border-dashed pt-2">
                  Firma Encargado de Depósito
                </div>
                <div className="mt-1 text-muted-foreground">
                  {movement.user_name}
                </div>
              </div>
              <div>
                <div className="mx-auto w-48 border-t border-dashed pt-2">
                  Firma Auditor / Supervisor
                </div>
                <div className="mt-1 text-muted-foreground">
                  Control de Auditoría
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
