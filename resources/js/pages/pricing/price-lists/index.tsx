import { Head, Link, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Power, Search, Tags } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
  store,
  toggleStatus,
  update,
} from '@/actions/App/Http/Controllers/Pricing/PriceListController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import TablePagination from '@/components/table-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
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
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import { index, show } from '@/routes/pricing/price-lists';
import type { BreadcrumbItem } from '@/types';

type PriceList = App.Data.Pricing.PriceListData;
type Props = { priceLists: PriceList[] };
type PriceListFormData = {
  name: string;
  description: string;
  scope: string;
  channel: string;
  valid_from: string;
  valid_to: string;
  is_active: boolean;
};

const SCOPE_OPTIONS = [
  {
    value: 'canal',
    label: 'De canal',
    hint: 'Precio base de un canal de venta. Solo puede haber una vigente por canal a la vez.',
  },
  {
    value: 'particular',
    label: 'Particular',
    hint: 'Precio preferencial para los clientes que la tengan asignada. Puede convivir con las de canal.',
  },
];

const CHANNEL_OPTIONS = [
  { value: 'general', label: 'General' },
  { value: 'mostrador', label: 'Mostrador' },
  { value: 'online', label: 'Online' },
];

const VALIDITY_BADGE_VARIANT: Record<
  string,
  'default' | 'secondary' | 'outline'
> = {
  vigente: 'default',
  futura: 'outline',
  vencida: 'secondary',
};

export default function PriceListsIndex({ priceLists = [] }: Props) {
  const [searchTerm, setSearchTerm] = useState('');
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [editingPriceList, setEditingPriceList] = useState<PriceList | null>(
    null,
  );
  const [currentPage, setCurrentPage] = useState(1);
  const PAGE_SIZE = 10;

  const createForm = useForm<PriceListFormData>({
    name: '',
    description: '',
    scope: 'canal',
    channel: '',
    valid_from: '',
    valid_to: '',
    is_active: true,
  });
  const editForm = useForm<PriceListFormData>({
    name: '',
    description: '',
    scope: 'canal',
    channel: '',
    valid_from: '',
    valid_to: '',
    is_active: true,
  });

  const filteredPriceLists = priceLists.filter((priceList) =>
    priceList.name.toLowerCase().includes(searchTerm.trim().toLowerCase()),
  );

  const totalPages = Math.max(
    1,
    Math.ceil(filteredPriceLists.length / PAGE_SIZE),
  );
  const safeCurrentPage = Math.min(currentPage, totalPages);
  const paginatedPriceLists = filteredPriceLists.slice(
    (safeCurrentPage - 1) * PAGE_SIZE,
    safeCurrentPage * PAGE_SIZE,
  );

  const openCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    setIsCreateOpen(true);
  };

  const openEdit = (priceList: PriceList) => {
    editForm.setData({
      name: priceList.name,
      description: priceList.description ?? '',
      scope: priceList.scope,
      channel: priceList.channel ?? '',
      valid_from: priceList.valid_from,
      valid_to: priceList.valid_to ?? '',
      is_active: priceList.is_active,
    });
    editForm.clearErrors();
    setEditingPriceList(priceList);
  };

  const submitCreate = (event: React.FormEvent) => {
    event.preventDefault();
    createForm.post(store.url(), {
      preserveScroll: true,
      onSuccess: () => {
        setIsCreateOpen(false);
        toast.success('Lista de precios creada correctamente');
      },
      onError: () => toast.error('Revisá los datos de la lista de precios'),
    });
  };

  const submitEdit = (event: React.FormEvent) => {
    event.preventDefault();

    if (!editingPriceList) {
      return;
    }

    editForm.put(update.url({ price_list: editingPriceList.id }), {
      preserveScroll: true,
      onSuccess: () => {
        setEditingPriceList(null);
        toast.success('Lista de precios actualizada correctamente');
      },
      onError: (errors) =>
        toast.error(
          errors.price_list ?? 'Revisá los datos de la lista de precios',
        ),
    });
  };

  const toggle = (priceList: PriceList) => {
    router.patch(
      toggleStatus.url({ price_list: priceList.id }),
      {},
      {
        preserveScroll: true,
        onSuccess: () =>
          toast.success(`Estado de "${priceList.name}" actualizado`),
        onError: (errors) =>
          toast.error(errors.price_list ?? 'No se pudo actualizar la lista'),
      },
    );
  };

  const formFields = (form: typeof createForm, prefix: 'create' | 'edit') => (
    <div className="grid gap-4 py-4">
      <div className="grid gap-2">
        <Label htmlFor={`${prefix}-price-list-name`}>Nombre *</Label>
        <Input
          id={`${prefix}-price-list-name`}
          value={form.data.name}
          onChange={(event) => form.setData('name', event.target.value)}
          required
        />
        <InputError message={form.errors.name} />
      </div>
      <div className="grid gap-2">
        <Label htmlFor={`${prefix}-price-list-scope`}>Tipo de lista *</Label>
        <Select
          value={form.data.scope}
          onValueChange={(value) => {
            form.setData('scope', value);

            if (value !== 'canal') {
              form.setData('channel', '');
            }
          }}
        >
          <SelectTrigger id={`${prefix}-price-list-scope`}>
            <SelectValue placeholder="Seleccioná el tipo" />
          </SelectTrigger>
          <SelectContent>
            {SCOPE_OPTIONS.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <p className="text-xs text-muted-foreground">
          {SCOPE_OPTIONS.find((option) => option.value === form.data.scope)
            ?.hint ?? ''}
        </p>
        <InputError message={form.errors.scope} />
      </div>
      {form.data.scope === 'canal' && (
        <div className="grid gap-2">
          <Label htmlFor={`${prefix}-price-list-channel`}>Canal *</Label>
          <Select
            value={form.data.channel}
            onValueChange={(value) => form.setData('channel', value)}
          >
            <SelectTrigger id={`${prefix}-price-list-channel`}>
              <SelectValue placeholder="Seleccioná el canal" />
            </SelectTrigger>
            <SelectContent>
              {CHANNEL_OPTIONS.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <InputError message={form.errors.channel} />
        </div>
      )}
      <div className="grid grid-cols-2 gap-4">
        <div className="grid gap-2">
          <Label htmlFor={`${prefix}-price-list-valid-from`}>
            Vigencia desde *
          </Label>
          <Input
            id={`${prefix}-price-list-valid-from`}
            type="date"
            value={form.data.valid_from}
            onChange={(event) => form.setData('valid_from', event.target.value)}
            required
          />
          <InputError message={form.errors.valid_from} />
        </div>
        <div className="grid gap-2">
          <Label htmlFor={`${prefix}-price-list-valid-to`}>
            Vigencia hasta
          </Label>
          <Input
            id={`${prefix}-price-list-valid-to`}
            type="date"
            value={form.data.valid_to}
            onChange={(event) => form.setData('valid_to', event.target.value)}
          />
          <InputError message={form.errors.valid_to} />
        </div>
      </div>
      <div className="grid gap-2">
        <Label htmlFor={`${prefix}-price-list-description`}>Descripción</Label>
        <Textarea
          id={`${prefix}-price-list-description`}
          value={form.data.description}
          onChange={(event) => form.setData('description', event.target.value)}
        />
        <InputError message={form.errors.description} />
      </div>
      <div className="flex items-center gap-2">
        <Checkbox
          id={`${prefix}-price-list-active`}
          checked={form.data.is_active}
          onCheckedChange={(checked) =>
            form.setData('is_active', checked === true)
          }
        />
        <Label htmlFor={`${prefix}-price-list-active`}>Lista activa</Label>
      </div>
      <InputError message={form.errors.is_active} />
    </div>
  );

  return (
    <>
      <Head title="Listas de Precios" />
      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <Heading
          title="Listas de Precios"
          description="Administrá las listas de precios por canal y su vigencia."
        />
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="relative max-w-sm flex-1">
            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder="Buscar lista..."
              value={searchTerm}
              onChange={(event) => {
                setSearchTerm(event.target.value);
                setCurrentPage(1);
              }}
              className="pl-9"
            />
          </div>
          <Button onClick={openCreate}>
            <Plus className="mr-1.5 size-4" />
            Nueva lista
          </Button>
        </div>
        <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nombre</TableHead>
                <TableHead>Tipo</TableHead>
                <TableHead>Canal</TableHead>
                <TableHead>Vigencia</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead>Vigencia calculada</TableHead>
                <TableHead>Artículos con precio</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredPriceLists.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={8}
                    className="py-12 text-center text-muted-foreground"
                  >
                    No se encontraron listas de precios registradas.
                  </TableCell>
                </TableRow>
              ) : (
                paginatedPriceLists.map((priceList) => (
                  <TableRow key={priceList.id}>
                    <TableCell className="font-medium">
                      <Link
                        href={show(priceList.id)}
                        className="underline-offset-4 hover:underline"
                      >
                        {priceList.name}
                      </Link>
                    </TableCell>
                    <TableCell>{priceList.scope_label}</TableCell>
                    <TableCell>
                      {priceList.channel_label ?? (
                        <span className="text-muted-foreground">—</span>
                      )}
                    </TableCell>
                    <TableCell>
                      {priceList.valid_from}
                      {priceList.valid_to
                        ? ` — ${priceList.valid_to}`
                        : ' — sin fin'}
                    </TableCell>
                    <TableCell>
                      <Badge
                        variant={priceList.is_active ? 'default' : 'secondary'}
                      >
                        {priceList.is_active ? 'Activa' : 'Inactiva'}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <Badge
                        variant={
                          VALIDITY_BADGE_VARIANT[priceList.validity_status] ??
                          'outline'
                        }
                      >
                        {priceList.validity_status_label}
                      </Badge>
                    </TableCell>
                    <TableCell>{priceList.articles_with_price_count}</TableCell>
                    <TableCell className="text-right">
                      <Button
                        variant="ghost"
                        size="icon"
                        asChild
                        aria-label={`Cargar precios de ${priceList.name}`}
                        title="Cargar precios"
                      >
                        <Link href={show(priceList.id)}>
                          <Tags className="size-4" />
                        </Link>
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => openEdit(priceList)}
                        aria-label={`Editar ${priceList.name}`}
                      >
                        <Pencil className="size-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => toggle(priceList)}
                        aria-label={`Cambiar estado de ${priceList.name}`}
                      >
                        <Power className="size-4" />
                      </Button>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
        <TablePagination
          currentPage={safeCurrentPage}
          totalPages={totalPages}
          totalItems={filteredPriceLists.length}
          pageSize={PAGE_SIZE}
          onPageChange={setCurrentPage}
          entityName="listas de precios"
        />
      </div>
      <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
        <DialogContent className="sm:max-w-md">
          <form onSubmit={submitCreate}>
            <DialogHeader>
              <DialogTitle>Nueva lista de precios</DialogTitle>
            </DialogHeader>
            {formFields(createForm, 'create')}
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setIsCreateOpen(false)}
              >
                Cancelar
              </Button>
              <Button type="submit" disabled={createForm.processing}>
                Crear lista
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
      <Dialog
        open={editingPriceList !== null}
        onOpenChange={(open) => !open && setEditingPriceList(null)}
      >
        <DialogContent className="sm:max-w-md">
          <form onSubmit={submitEdit}>
            <DialogHeader>
              <DialogTitle>Editar lista de precios</DialogTitle>
            </DialogHeader>
            {formFields(editForm, 'edit')}
            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setEditingPriceList(null)}
              >
                Cancelar
              </Button>
              <Button type="submit" disabled={editForm.processing}>
                Guardar cambios
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </>
  );
}

PriceListsIndex.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Listas de Precios', href: index() },
  ] satisfies BreadcrumbItem[],
};
