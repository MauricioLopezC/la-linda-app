import { router } from '@inertiajs/react';
import { Filter, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';

type Props = {
  filters: App.Data.Reporting.DashboardFiltersData;
  branches: App.Data.Reporting.DashboardBranchOptionData[];
};

const channels = [
  { value: 'mostrador', label: 'Mostrador' },
  { value: 'online', label: 'Online' },
];

export default function DashboardFilters({ filters, branches }: Props) {
  const [dateFrom, setDateFrom] = useState(filters.date_from);
  const [dateTo, setDateTo] = useState(filters.date_to);
  const [branchId, setBranchId] = useState(
    filters.branch_id === null ? 'all' : String(filters.branch_id),
  );
  const [channel, setChannel] = useState(filters.channel ?? 'all');

  const applyFilters = () =>
    router.get(
      dashboard.url({
        query: {
          date_from: dateFrom || undefined,
          date_to: dateTo || undefined,
          branch_id: branchId === 'all' ? undefined : branchId,
          channel: channel === 'all' ? undefined : channel,
        },
      }),
      {},
      { preserveState: true, preserveScroll: true },
    );

  const resetFilters = () =>
    router.get(dashboard.url(), {}, { preserveScroll: true });

  return (
    <Card>
      <CardContent className="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-12">
        <div className="space-y-1.5 xl:col-span-2">
          <Label htmlFor="date_from">Fecha desde</Label>
          <Input
            id="date_from"
            type="date"
            value={dateFrom}
            onChange={(event) => setDateFrom(event.target.value)}
          />
        </div>

        <div className="space-y-1.5 xl:col-span-2">
          <Label htmlFor="date_to">Fecha hasta</Label>
          <Input
            id="date_to"
            type="date"
            min={dateFrom}
            value={dateTo}
            onChange={(event) => setDateTo(event.target.value)}
          />
        </div>

        <FilterSelect
          label="Sucursal"
          value={branchId}
          onChange={setBranchId}
          options={branches.map((branch) => ({
            value: String(branch.id),
            label: branch.name,
          }))}
        />

        <FilterSelect
          label="Canal"
          value={channel}
          onChange={setChannel}
          options={channels}
        />

        <div className="flex items-end gap-2 md:col-span-2 xl:col-span-4 xl:justify-end">
          <Button onClick={applyFilters}>
            <Filter className="size-4" />
            Filtrar
          </Button>
          <Button
            variant="outline"
            size="icon"
            onClick={resetFilters}
            aria-label="Limpiar filtros"
          >
            <RotateCcw className="size-4" />
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}

function FilterSelect({
  label,
  value,
  onChange,
  options,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  options: Array<{ value: string; label: string }>;
}) {
  return (
    <div className="space-y-1.5 xl:col-span-2">
      <Label>{label}</Label>
      <Select value={value} onValueChange={onChange}>
        <SelectTrigger className="w-full">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">Todos</SelectItem>
          {options.map((option) => (
            <SelectItem key={option.value} value={option.value}>
              {option.label}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );
}
