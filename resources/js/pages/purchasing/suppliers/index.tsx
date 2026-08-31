import { Head, router, useForm } from '@inertiajs/react';
import {
  FileText,
  Landmark,
  MapPin,
  Pencil,
  Plus,
  Power,
  Search,
  Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
  destroy,
  store,
  toggleStatus,
  update,
} from '@/actions/App/Http/Controllers/Purchasing/SupplierController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Dialog,
  DialogContent,
  DialogDescription,
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
import { index } from '@/routes/purchasing/suppliers';
import type { BreadcrumbItem } from '@/types';

type Supplier = App.Data.Purchasing.SupplierData;
type TaxConditionOption = App.Data.Purchasing.SupplierTaxConditionOptionData;

type Props = {
  suppliers: Supplier[];
  taxConditions: TaxConditionOption[];
  filters: {
    search: string;
    tax_condition: string;
    status: string;
  };
};

type SupplierFormData = {
  business_name: string;
  tax_id: string;
  tax_condition: string;
  address: string;
  rubro: string;
  bank_account: string;
  commercial_terms: string;
  is_active: boolean;
};

export default function SuppliersIndex({
  suppliers = [],
  taxConditions = [],
  filters,
}: Props) {
  const [searchTerm, setSearchTerm] = useState(filters.search ?? '');
  const [selectedTaxCondition, setSelectedTaxCondition] = useState(
    filters.tax_condition ?? 'all',
  );
  const [selectedStatus, setSelectedStatus] = useState(filters.status ?? 'all');

  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [editingSupplier, setEditingSupplier] = useState<Supplier | null>(null);
  const [deletingSupplier, setDeletingSupplier] = useState<Supplier | null>(
    null,
  );

  const createForm = useForm<SupplierFormData>({
    business_name: '',
    tax_id: '',
    tax_condition: taxConditions[0]?.value ?? 'responsable_inscripto',
    address: '',
    rubro: '',
    bank_account: '',
    commercial_terms: '',
    is_active: true,
  });

  const editForm = useForm<SupplierFormData>({
    business_name: '',
    tax_id: '',
    tax_condition: 'responsable_inscripto',
    address: '',
    rubro: '',
    bank_account: '',
    commercial_terms: '',
    is_active: true,
  });

  const handleApplyFilter = (
    newSearch: string,
    newTaxCondition: string,
    newStatus: string,
  ) => {
    router.get(
      index.url(),
      {
        search: newSearch || undefined,
        tax_condition: newTaxCondition !== 'all' ? newTaxCondition : undefined,
        status: newStatus !== 'all' ? newStatus : undefined,
      },
      {
        preserveState: true,
        preserveScroll: true,
      },
    );
  };

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    handleApplyFilter(searchTerm, selectedTaxCondition, selectedStatus);
  };

  const handleTaxConditionFilterChange = (val: string) => {
    setSelectedTaxCondition(val);
    handleApplyFilter(searchTerm, val, selectedStatus);
  };

  const handleStatusFilterChange = (val: string) => {
    setSelectedStatus(val);
    handleApplyFilter(searchTerm, selectedTaxCondition, val);
  };

  const handleOpenCreate = () => {
    createForm.reset();
    createForm.setData({
      business_name: '',
      tax_id: '',
      tax_condition: taxConditions[0]?.value ?? 'responsable_inscripto',
      address: '',
      rubro: '',
      bank_account: '',
      commercial_terms: '',
      is_active: true,
    });
    createForm.clearErrors();
    setIsCreateOpen(true);
  };

  const handleCreateSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    createForm.post(store.url(), {
      preserveScroll: true,
      onSuccess: () => {
        setIsCreateOpen(false);
        createForm.reset();
        toast.success('Proveedor registrado correctamente');
      },
      onError: () => {
        toast.error('Revisá los datos del proveedor ingresado');
      },
    });
  };

  const handleOpenEdit = (supplier: Supplier) => {
    setEditingSupplier(supplier);
    editForm.setData({
      business_name: supplier.business_name,
      tax_id: supplier.tax_id_raw,
      tax_condition: supplier.tax_condition,
      address: supplier.address ?? '',
      rubro: supplier.rubro ?? '',
      bank_account: supplier.bank_account ?? '',
      commercial_terms: supplier.commercial_terms ?? '',
      is_active: supplier.is_active,
    });
    editForm.clearErrors();
  };

  const handleEditSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!editingSupplier) {
      return;
    }

    editForm.put(update.url({ supplier: editingSupplier.id }), {
      preserveScroll: true,
      onSuccess: () => {
        setEditingSupplier(null);
        toast.success('Proveedor actualizado correctamente');
      },
      onError: () => {
        toast.error('Error al actualizar el proveedor');
      },
    });
  };

  const handleToggleStatus = (supplier: Supplier) => {
    router.patch(
      toggleStatus.url({ supplier: supplier.id }),
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(
            `Estado de "${supplier.business_name}" actualizado a ${supplier.is_active ? 'Inactivo' : 'Activo'}`,
          );
        },
        onError: (errors) => {
          toast.error(errors.supplier ?? 'No se pudo actualizar el estado');
        },
      },
    );
  };

  const handleDeleteConfirm = () => {
    if (!deletingSupplier) {
      return;
    }

    router.delete(destroy.url({ supplier: deletingSupplier.id }), {
      preserveScroll: true,
      onSuccess: () => {
        setDeletingSupplier(null);
        toast.success('Proveedor eliminado correctamente');
      },
      onError: (errors) => {
        toast.error(
          errors.supplier ??
            'No se pudo eliminar el proveedor. Realizá la baja lógica desactivándolo.',
        );
      },
    });
  };

  return (
    <>
      <Head title="Proveedores" />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <Heading
            title="Proveedores"
            description="Administrá el catálogo de proveedores, datos comerciales, CUIT y cuentas bancarias."
          />
        </div>

        {/* Toolbar & Filters */}
        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
            {/* Search Input */}
            <form
              onSubmit={handleSearchSubmit}
              className="relative max-w-sm flex-1"
            >
              <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
              <Input
                placeholder="Buscar por razón social, CUIT o rubro..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-9"
              />
            </form>

            {/* Tax Condition Select */}
            <Select
              value={selectedTaxCondition}
              onValueChange={handleTaxConditionFilterChange}
            >
              <SelectTrigger className="w-full sm:w-[220px]">
                <SelectValue placeholder="Condición fiscal" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todas las condiciones</SelectItem>
                {taxConditions.map((condition) => (
                  <SelectItem key={condition.value} value={condition.value}>
                    {condition.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            {/* Status Select */}
            <Select
              value={selectedStatus}
              onValueChange={handleStatusFilterChange}
            >
              <SelectTrigger className="w-full sm:w-[150px]">
                <SelectValue placeholder="Estado" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos los estados</SelectItem>
                <SelectItem value="active">Activos</SelectItem>
                <SelectItem value="inactive">Inactivos</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <Button onClick={handleOpenCreate}>
            <Plus className="mr-1.5 size-4" />
            Nuevo Proveedor
          </Button>
        </div>

        {/* Table */}
        <div className="overflow-hidden rounded-xl border border-sidebar-border bg-card shadow-sm">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Razón Social / Rubro</TableHead>
                <TableHead>CUIT</TableHead>
                <TableHead>Condición Fiscal</TableHead>
                <TableHead>Domicilio / Contacto</TableHead>
                <TableHead>Condiciones Comerciales</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {suppliers.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={7}
                    className="py-12 text-center text-muted-foreground"
                  >
                    No se encontraron proveedores registrados con los filtros
                    aplicados.
                  </TableCell>
                </TableRow>
              ) : (
                suppliers.map((supplier) => (
                  <TableRow key={supplier.id}>
                    <TableCell className="font-medium">
                      <div className="flex flex-col">
                        <span className="font-semibold text-foreground">
                          {supplier.business_name}
                        </span>
                        {supplier.rubro && (
                          <span className="text-xs text-muted-foreground">
                            Rubro: {supplier.rubro}
                          </span>
                        )}
                      </div>
                    </TableCell>
                    <TableCell>
                      <span className="font-mono text-sm font-medium">
                        {supplier.tax_id}
                      </span>
                    </TableCell>
                    <TableCell>
                      <Badge variant="outline" className="font-normal">
                        {supplier.tax_condition_label}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <div className="flex flex-col gap-1 text-xs text-muted-foreground">
                        {supplier.address && (
                          <div className="flex items-center gap-1.5">
                            <MapPin className="size-3 shrink-0" />
                            <span className="max-w-[200px] truncate">
                              {supplier.address}
                            </span>
                          </div>
                        )}
                        {supplier.bank_account && (
                          <div className="flex items-center gap-1.5">
                            <Landmark className="size-3 shrink-0" />
                            <span className="max-w-[200px] truncate">
                              {supplier.bank_account}
                            </span>
                          </div>
                        )}
                        {!supplier.address && !supplier.bank_account && '—'}
                      </div>
                    </TableCell>
                    <TableCell>
                      {supplier.commercial_terms ? (
                        <div
                          className="flex max-w-[220px] items-center gap-1 truncate text-xs text-muted-foreground"
                          title={supplier.commercial_terms}
                        >
                          <FileText className="size-3 shrink-0 text-primary" />
                          <span className="truncate">
                            {supplier.commercial_terms}
                          </span>
                        </div>
                      ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                      )}
                    </TableCell>
                    <TableCell>
                      <Badge
                        variant={supplier.is_active ? 'default' : 'secondary'}
                      >
                        {supplier.is_active ? 'Activo' : 'Inactivo'}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex items-center justify-end gap-1">
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={() => handleOpenEdit(supplier)}
                          aria-label={`Editar ${supplier.business_name}`}
                        >
                          <Pencil className="size-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={() => handleToggleStatus(supplier)}
                          aria-label={`Cambiar estado de ${supplier.business_name}`}
                        >
                          <Power className="size-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={() => setDeletingSupplier(supplier)}
                          aria-label={`Eliminar ${supplier.business_name}`}
                          className="text-destructive hover:text-destructive"
                        >
                          <Trash2 className="size-4" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      </div>

      {/* DIALOG: Create Supplier */}
      <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
          <form onSubmit={handleCreateSubmit}>
            <DialogHeader>
              <DialogTitle>Nuevo Proveedor</DialogTitle>
              <DialogDescription>
                Registrá un nuevo proveedor en el sistema. Todos los campos
                marcados con * son obligatorios.
              </DialogDescription>
            </DialogHeader>
            <div className="grid gap-4 py-4">
              <div className="grid gap-2">
                <Label htmlFor="create-business-name">Razón Social *</Label>
                <Input
                  id="create-business-name"
                  placeholder="Ej. Molinos Río de la Plata S.A."
                  value={createForm.data.business_name}
                  onChange={(e) =>
                    createForm.setData('business_name', e.target.value)
                  }
                  required
                />
                <InputError message={createForm.errors.business_name} />
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                  <Label htmlFor="create-tax-id">CUIT *</Label>
                  <Input
                    id="create-tax-id"
                    placeholder="30-50085862-8"
                    value={createForm.data.tax_id}
                    onChange={(e) =>
                      createForm.setData('tax_id', e.target.value)
                    }
                    required
                  />
                  <InputError message={createForm.errors.tax_id} />
                </div>

                <div className="grid gap-2">
                  <Label htmlFor="create-tax-condition">
                    Condición Fiscal *
                  </Label>
                  <Select
                    value={createForm.data.tax_condition}
                    onValueChange={(val) =>
                      createForm.setData('tax_condition', val)
                    }
                  >
                    <SelectTrigger id="create-tax-condition">
                      <SelectValue placeholder="Seleccioná condición" />
                    </SelectTrigger>
                    <SelectContent>
                      {taxConditions.map((cond) => (
                        <SelectItem key={cond.value} value={cond.value}>
                          {cond.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <InputError message={createForm.errors.tax_condition} />
                </div>
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                  <Label htmlFor="create-rubro">Rubro</Label>
                  <Input
                    id="create-rubro"
                    placeholder="Ej. Alimentos secos, Lácteos..."
                    value={createForm.data.rubro}
                    onChange={(e) =>
                      createForm.setData('rubro', e.target.value)
                    }
                  />
                  <InputError message={createForm.errors.rubro} />
                </div>

                <div className="grid gap-2">
                  <Label htmlFor="create-address">Domicilio Comercial</Label>
                  <Input
                    id="create-address"
                    placeholder="Ej. Av. Libertador 1234, CABA"
                    value={createForm.data.address}
                    onChange={(e) =>
                      createForm.setData('address', e.target.value)
                    }
                  />
                  <InputError message={createForm.errors.address} />
                </div>
              </div>

              <div className="grid gap-2">
                <Label htmlFor="create-bank-account">
                  Cuenta Bancaria / CBU / Alias
                </Label>
                <Input
                  id="create-bank-account"
                  placeholder="Ej. CBU 0170099... / Alias: MOLINOS.PAGOS"
                  value={createForm.data.bank_account}
                  onChange={(e) =>
                    createForm.setData('bank_account', e.target.value)
                  }
                />
                <InputError message={createForm.errors.bank_account} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="create-commercial-terms">
                  Condiciones Comerciales Pactadas
                </Label>
                <Textarea
                  id="create-commercial-terms"
                  placeholder="Plazos de pago, descuentos comerciales acordados, bonificaciones por volumen o flete..."
                  value={createForm.data.commercial_terms}
                  onChange={(e) =>
                    createForm.setData('commercial_terms', e.target.value)
                  }
                  rows={3}
                />
                <InputError message={createForm.errors.commercial_terms} />
              </div>

              <div className="flex items-center gap-2 pt-1">
                <Checkbox
                  id="create-supplier-active"
                  checked={createForm.data.is_active}
                  onCheckedChange={(checked) =>
                    createForm.setData('is_active', checked === true)
                  }
                />
                <Label htmlFor="create-supplier-active">Proveedor activo</Label>
              </div>
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setIsCreateOpen(false)}
              >
                Cancelar
              </Button>
              <Button type="submit" disabled={createForm.processing}>
                Registrar Proveedor
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* DIALOG: Edit Supplier */}
      <Dialog
        open={editingSupplier !== null}
        onOpenChange={(open) => !open && setEditingSupplier(null)}
      >
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
          <form onSubmit={handleEditSubmit}>
            <DialogHeader>
              <DialogTitle>Editar Proveedor</DialogTitle>
              <DialogDescription>
                Modificá los datos del proveedor.
              </DialogDescription>
            </DialogHeader>
            <div className="grid gap-4 py-4">
              <div className="grid gap-2">
                <Label htmlFor="edit-business-name">Razón Social *</Label>
                <Input
                  id="edit-business-name"
                  value={editForm.data.business_name}
                  onChange={(e) =>
                    editForm.setData('business_name', e.target.value)
                  }
                  required
                />
                <InputError message={editForm.errors.business_name} />
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                  <Label htmlFor="edit-tax-id">CUIT *</Label>
                  <Input
                    id="edit-tax-id"
                    value={editForm.data.tax_id}
                    onChange={(e) => editForm.setData('tax_id', e.target.value)}
                    disabled={editingSupplier?.has_associated_records}
                    required
                  />
                  {editingSupplier?.has_associated_records && (
                    <p className="text-xs text-muted-foreground">
                      El CUIT no se puede modificar porque tiene registros
                      asociados.
                    </p>
                  )}
                  <InputError message={editForm.errors.tax_id} />
                </div>

                <div className="grid gap-2">
                  <Label htmlFor="edit-tax-condition">Condición Fiscal *</Label>
                  <Select
                    value={editForm.data.tax_condition}
                    onValueChange={(val) =>
                      editForm.setData('tax_condition', val)
                    }
                  >
                    <SelectTrigger id="edit-tax-condition">
                      <SelectValue placeholder="Seleccioná condición" />
                    </SelectTrigger>
                    <SelectContent>
                      {taxConditions.map((cond) => (
                        <SelectItem key={cond.value} value={cond.value}>
                          {cond.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <InputError message={editForm.errors.tax_condition} />
                </div>
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                  <Label htmlFor="edit-rubro">Rubro</Label>
                  <Input
                    id="edit-rubro"
                    value={editForm.data.rubro}
                    onChange={(e) => editForm.setData('rubro', e.target.value)}
                  />
                  <InputError message={editForm.errors.rubro} />
                </div>

                <div className="grid gap-2">
                  <Label htmlFor="edit-address">Domicilio Comercial</Label>
                  <Input
                    id="edit-address"
                    value={editForm.data.address}
                    onChange={(e) =>
                      editForm.setData('address', e.target.value)
                    }
                  />
                  <InputError message={editForm.errors.address} />
                </div>
              </div>

              <div className="grid gap-2">
                <Label htmlFor="edit-bank-account">
                  Cuenta Bancaria / CBU / Alias
                </Label>
                <Input
                  id="edit-bank-account"
                  value={editForm.data.bank_account}
                  onChange={(e) =>
                    editForm.setData('bank_account', e.target.value)
                  }
                />
                <InputError message={editForm.errors.bank_account} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="edit-commercial-terms">
                  Condiciones Comerciales Pactadas
                </Label>
                <Textarea
                  id="edit-commercial-terms"
                  value={editForm.data.commercial_terms}
                  onChange={(e) =>
                    editForm.setData('commercial_terms', e.target.value)
                  }
                  rows={3}
                />
                <InputError message={editForm.errors.commercial_terms} />
              </div>

              <div className="flex items-center gap-2 pt-1">
                <Checkbox
                  id="edit-supplier-active"
                  checked={editForm.data.is_active}
                  onCheckedChange={(checked) =>
                    editForm.setData('is_active', checked === true)
                  }
                />
                <Label htmlFor="edit-supplier-active">Proveedor activo</Label>
              </div>
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setEditingSupplier(null)}
              >
                Cancelar
              </Button>
              <Button type="submit" disabled={editForm.processing}>
                Guardar Cambios
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* DIALOG: Delete Supplier Confirmation */}
      <Dialog
        open={deletingSupplier !== null}
        onOpenChange={(open) => !open && setDeletingSupplier(null)}
      >
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Eliminar Proveedor</DialogTitle>
            <DialogDescription>
              ¿Estás seguro de que deseás eliminar a &quot;
              {deletingSupplier?.business_name}&quot;? Esta acción no se puede
              deshacer. Si el proveedor tiene comprobantes o pagos asociados, la
              eliminación física será rechazada y deberás optar por la baja
              lógica (desactivación).
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => setDeletingSupplier(null)}
            >
              Cancelar
            </Button>
            <Button
              type="button"
              variant="destructive"
              onClick={handleDeleteConfirm}
            >
              Eliminar Proveedor
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

SuppliersIndex.layout = {
  breadcrumbs: [
    {
      title: 'Dashboard',
      href: dashboard(),
    },
    {
      title: 'Proveedores',
      href: index(),
    },
  ] satisfies BreadcrumbItem[],
};
