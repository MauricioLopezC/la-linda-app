import { Head, useForm } from '@inertiajs/react';
import { Vault } from 'lucide-react';
import { store } from '@/actions/App/Http/Controllers/Sales/CashSessionController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
  TableFooter,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { formatCurrency } from '@/lib/utils';
import { dashboard } from '@/routes';
import { create } from '@/routes/sales/cash-sessions';
import type { BreadcrumbItem } from '@/types';

type PointOfSale = App.Data.Sales.CashSessionPointOfSaleData;
type Denomination = App.Data.Sales.CashDenominationData;

type Props = {
  pointsOfSale: PointOfSale[];
  denominations: Denomination[];
};

/**
 * A blank or invalid quantity counts as zero for the live total; the backend
 * validates the real input.
 */
function toQuantity(value: string): number {
  const quantity = Number(value);

  return Number.isInteger(quantity) && quantity > 0 ? quantity : 0;
}

export default function OpenCashSession({
  pointsOfSale = [],
  denominations = [],
}: Props) {
  const form = useForm({
    point_of_sale_id: '',
    counts: Object.fromEntries(
      denominations.map((denomination) => [String(denomination.value), '0']),
    ) as Record<string, string>,
  });

  const errors = form.errors as Record<string, string | undefined>;
  const total = denominations.reduce(
    (sum, denomination) =>
      sum +
      denomination.value *
        toQuantity(form.data.counts[String(denomination.value)] ?? ''),
    0,
  );
  const hasFreePointOfSale = pointsOfSale.some(
    (pos) => pos.open_session_user_name === null,
  );

  const setQuantity = (denomination: number, value: string) => {
    form.setData('counts', {
      ...form.data.counts,
      [String(denomination)]: value,
    });
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    form.transform((data) => ({
      point_of_sale_id: Number(data.point_of_sale_id),
      counts: Object.fromEntries(
        Object.entries(data.counts).map(([denomination, quantity]) => [
          denomination,
          quantity === '' ? 0 : Number(quantity),
        ]),
      ),
    }));
    form.post(store.url());
  };

  return (
    <>
      <Head title="Abrir caja" />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <Heading
          title="Abrir caja"
          description="Elegí la caja y contá los billetes de cambio con los que arrancás el turno."
        />

        {!hasFreePointOfSale && (
          <p className="text-sm text-muted-foreground">
            No hay cajas libres: todas las cajas activas tienen un turno
            abierto.
          </p>
        )}

        <form onSubmit={handleSubmit} className="flex max-w-xl flex-col gap-6">
          <div className="space-y-1.5">
            <Label htmlFor="point_of_sale_id">Caja</Label>
            <Select
              value={form.data.point_of_sale_id}
              onValueChange={(value) => form.setData('point_of_sale_id', value)}
            >
              <SelectTrigger id="point_of_sale_id" className="w-full">
                <SelectValue placeholder="Seleccioná una caja" />
              </SelectTrigger>
              <SelectContent>
                {pointsOfSale.map((pos) => (
                  <SelectItem
                    key={pos.id}
                    value={String(pos.id)}
                    disabled={pos.open_session_user_name !== null}
                  >
                    Caja {pos.number} · {pos.branch_name}
                    {pos.open_session_user_name !== null &&
                      ` (abierta por ${pos.open_session_user_name})`}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <InputError message={errors.point_of_sale_id} />
          </div>

          <div className="space-y-1.5">
            <Label>Conteo de billetes</Label>
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Billete</TableHead>
                    <TableHead className="w-32">Cantidad</TableHead>
                    <TableHead className="text-right">Subtotal</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {denominations.map((denomination) => {
                    const key = String(denomination.value);
                    const quantity = form.data.counts[key] ?? '';

                    return (
                      <TableRow key={key}>
                        <TableCell className="font-medium">
                          {denomination.label}
                        </TableCell>
                        <TableCell>
                          <Input
                            type="number"
                            inputMode="numeric"
                            min={0}
                            step={1}
                            aria-label={`Cantidad de billetes de ${denomination.label}`}
                            value={quantity}
                            onChange={(e) =>
                              setQuantity(denomination.value, e.target.value)
                            }
                            onFocus={(e) => e.target.select()}
                          />
                          <InputError message={errors[`counts.${key}`]} />
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                          {formatCurrency(
                            denomination.value * toQuantity(quantity),
                          )}
                        </TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
                <TableFooter>
                  <TableRow>
                    <TableCell colSpan={2} className="font-semibold">
                      Fondo inicial
                    </TableCell>
                    <TableCell className="text-right text-base font-semibold tabular-nums">
                      {formatCurrency(total)}
                    </TableCell>
                  </TableRow>
                </TableFooter>
              </Table>
            </div>
            <InputError message={errors.counts} />
          </div>

          <div>
            <Button
              type="submit"
              disabled={form.processing || !hasFreePointOfSale}
            >
              <Vault className="mr-1.5 size-4" />
              Abrir caja
            </Button>
          </div>
        </form>
      </div>
    </>
  );
}

OpenCashSession.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Abrir caja', href: create() },
  ] satisfies BreadcrumbItem[],
};
