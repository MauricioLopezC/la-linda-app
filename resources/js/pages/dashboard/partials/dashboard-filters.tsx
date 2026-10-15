import type { Errors } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { Filter, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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
  const [errors, setErrors] = useState<Errors>({});

  const applyFilters = () => {
    setErrors({});

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
      {
        preserveState: true,
        preserveScroll: true,
        onError: setErrors,
        onSuccess: () => setErrors({}),
      },
    );
  };

  const resetFilters = () => {
    setErrors({});
    router.get(dashboard.url(), {}, { preserveScroll: true });
  };

  return (
    <Card>
      <CardContent className="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-12">
        <div className="space-y-1.5 xl:col-span-2">
          <Label htmlFor="date_from">Fecha desde</Label>
          <Input
            id="date_from"
            type="date"
            value={dateFrom}
            aria-invalid={Boolean(errors.date_from)}
            aria-describedby={errors.date_from ? 'date_from-error' : undefined}
            onChange={(event) => setDateFrom(event.target.value)}
          />
          <InputError id="date_from-error" message={errors.date_from} />
        </div>

        <div className="space-y-1.5 xl:col-span-2">
          <Label htmlFor="date_to">Fecha hasta</Label>
          <Input
            id="date_to"
            type="date"
            min={dateFrom}
            value={dateTo}
            aria-invalid={Boolean(errors.date_to)}
            aria-describedby={errors.date_to ? 'date_to-error' : undefined}
            onChange={(event) => setDateTo(event.target.value)}
          />
          <InputError id="date_to-error" message={errors.date_to} />
        </div>

        <FilterSelect
          id="branch_id"
          label="Sucursal"
          error={errors.branch_id}
          value={branchId}
          onChange={setBranchId}
          options={branches.map((branch) => ({
            value: String(branch.id),
            label: branch.name,
          }))}
        />

        <FilterSelect
          id="channel"
          label="Canal"
          error={errors.channel}
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
  id,
  label,
  error,
  value,
  onChange,
  options,
}: {
  id: string;
  label: string;
  error?: string;
  value: string;
  onChange: (value: string) => void;
  options: Array<{ value: string; label: string }>;
}) {
  return (
    <div className="space-y-1.5 xl:col-span-2">
      <Label htmlFor={id}>{label}</Label>
      <Select value={value} onValueChange={onChange}>
        <SelectTrigger
          id={id}
          className="w-full"
          aria-invalid={Boolean(error)}
          aria-describedby={error ? `${id}-error` : undefined}
        >
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
      <InputError id={`${id}-error`} message={error} />
    </div>
  );
}
