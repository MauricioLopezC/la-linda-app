import { Head, router, useForm } from '@inertiajs/react';
import {
  Building2,
  Mail,
  MapPin,
  Pencil,
  Phone,
  Plus,
  Power,
  Search,
  ShieldCheck,
  Trash2,
  User,
} from 'lucide-react';
import React, { useState } from 'react';
import { toast } from 'sonner';
import {
  destroy,
  store,
  toggleStatus,
  update,
} from '@/actions/App/Http/Controllers/Customers/CustomerController';
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
import { dashboard } from '@/routes';
import { index } from '@/routes/customers';
import type { BreadcrumbItem } from '@/types';

type Customer = App.Data.Customers.CustomerData;

type Option = {
  value: string;
  label: string;
};

type Props = {
  customers: Customer[];
  taxConditions: Option[];
  personTypes: Option[];
  idTypes: Option[];
  filters: {
    search: string;
    tax_condition: string;
    person_type: string;
    status: string;
  };
};

type CustomerFormData = {
  person_type: string;
  name: string;
  id_type: string;
  id_number: string;
  tax_condition: string;
  address: string;
  phone: string;
  email: string;
  is_active: boolean;
};

export default function CustomersIndex({
  customers = [],
  taxConditions = [],
  personTypes = [],
  idTypes = [],
  filters,
}: Props) {
  const [searchTerm, setSearchTerm] = useState(filters.search ?? '');
  const [selectedTaxCondition, setSelectedTaxCondition] = useState(
    filters.tax_condition ?? 'all',
  );
  const [selectedPersonType, setSelectedPersonType] = useState(
    filters.person_type ?? 'all',
  );
  const [selectedStatus, setSelectedStatus] = useState(filters.status ?? 'all');

  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [editingCustomer, setEditingCustomer] = useState<Customer | null>(null);
  const [deletingCustomer, setDeletingCustomer] = useState<Customer | null>(
    null,
  );

  const createForm = useForm<CustomerFormData>({
    person_type: 'fisica',
    name: '',
    id_type: 'sin_identificar',
    id_number: '',
    tax_condition: 'consumidor_final',
    address: '',
    phone: '',
    email: '',
    is_active: true,
  });

  const editForm = useForm<CustomerFormData>({
    person_type: 'fisica',
    name: '',
    id_type: 'cuit',
    id_number: '',
    tax_condition: 'responsable_inscripto',
    address: '',
    phone: '',
    email: '',
    is_active: true,
  });

  const handleApplyFilter = (
    newSearch: string,
    newTaxCondition: string,
    newPersonType: string,
    newStatus: string,
  ) => {
    router.get(
      index.url(),
      {
        search: newSearch || undefined,
        tax_condition: newTaxCondition !== 'all' ? newTaxCondition : undefined,
        person_type: newPersonType !== 'all' ? newPersonType : undefined,
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
    handleApplyFilter(
      searchTerm,
      selectedTaxCondition,
      selectedPersonType,
      selectedStatus,
    );
  };

  const handleTaxConditionFilterChange = (val: string) => {
    setSelectedTaxCondition(val);
    handleApplyFilter(searchTerm, val, selectedPersonType, selectedStatus);
  };

  const handlePersonTypeFilterChange = (val: string) => {
    setSelectedPersonType(val);
    handleApplyFilter(searchTerm, selectedTaxCondition, val, selectedStatus);
  };

  const handleStatusFilterChange = (val: string) => {
    setSelectedStatus(val);
    handleApplyFilter(
      searchTerm,
      selectedTaxCondition,
      selectedPersonType,
      val,
    );
  };

  const handleResetFilters = () => {
    setSearchTerm('');
    setSelectedTaxCondition('all');
    setSelectedPersonType('all');
    setSelectedStatus('all');
    router.get(index.url(), {}, { preserveState: true, preserveScroll: true });
  };

  const handleOpenCreate = () => {
    createForm.reset();
    createForm.setData({
      person_type: 'fisica',
      name: '',
      id_type: 'sin_identificar',
      id_number: '',
      tax_condition: 'consumidor_final',
      address: '',
      phone: '',
      email: '',
      is_active: true,
    });
    createForm.clearErrors();
    setIsCreateOpen(true);
  };

  // Helper para reactividad en formulario de alta
  const handleCreateTaxConditionChange = (val: string) => {
    let nextIdType = createForm.data.id_type;

    if (val !== 'consumidor_final') {
      nextIdType = 'cuit';
    } else if (createForm.data.person_type === 'juridica') {
      nextIdType = 'cuit';
    } else if (nextIdType === 'cuit') {
      nextIdType = 'sin_identificar';
    }

    createForm.setData((prev) => ({
      ...prev,
      tax_condition: val,
      id_type: nextIdType,
      id_number: nextIdType === 'sin_identificar' ? '' : prev.id_number,
    }));
  };

  const handleCreatePersonTypeChange = (val: string) => {
    let nextIdType = createForm.data.id_type;

    if (val === 'juridica') {
      nextIdType = 'cuit';
    } else if (createForm.data.tax_condition === 'consumidor_final') {
      nextIdType = 'sin_identificar';
    }

    createForm.setData((prev) => ({
      ...prev,
      person_type: val,
      id_type: nextIdType,
      id_number: nextIdType === 'sin_identificar' ? '' : prev.id_number,
    }));
  };

  const handleCreateSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    createForm.post(store.url(), {
      preserveScroll: true,
      onSuccess: () => {
        setIsCreateOpen(false);
        createForm.reset();
        toast.success('Cliente registrado correctamente');
      },
      onError: () => {
        toast.error('Revisá los errores en el formulario');
      },
    });
  };

  const handleOpenEdit = (customer: Customer) => {
    if (customer.is_default) {
      toast.warning(
        'El cliente por defecto Consumidor Final no puede ser modificado.',
      );

      return;
    }

    setEditingCustomer(customer);
    editForm.setData({
      person_type: customer.person_type,
      name: customer.name,
      id_type: customer.id_type,
      id_number: customer.id_number_raw ?? '',
      tax_condition: customer.tax_condition,
      address: customer.address ?? '',
      phone: customer.phone ?? '',
      email: customer.email ?? '',
      is_active: customer.is_active,
    });
    editForm.clearErrors();
  };

  const handleEditTaxConditionChange = (val: string) => {
    let nextIdType = editForm.data.id_type;

    if (val !== 'consumidor_final') {
      nextIdType = 'cuit';
    } else if (editForm.data.person_type === 'juridica') {
      nextIdType = 'cuit';
    } else if (nextIdType === 'cuit') {
      nextIdType = 'sin_identificar';
    }

    editForm.setData((prev) => ({
      ...prev,
      tax_condition: val,
      id_type: nextIdType,
      id_number: nextIdType === 'sin_identificar' ? '' : prev.id_number,
    }));
  };

  const handleEditPersonTypeChange = (val: string) => {
    let nextIdType = editForm.data.id_type;

    if (val === 'juridica') {
      nextIdType = 'cuit';
    } else if (editForm.data.tax_condition === 'consumidor_final') {
      nextIdType = 'sin_identificar';
    }

    editForm.setData((prev) => ({
      ...prev,
      person_type: val,
      id_type: nextIdType,
      id_number: nextIdType === 'sin_identificar' ? '' : prev.id_number,
    }));
  };

  const handleEditSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!editingCustomer) {
      return;
    }

    editForm.put(update.url({ customer: editingCustomer.id }), {
      preserveScroll: true,
      onSuccess: () => {
        setEditingCustomer(null);
        toast.success('Cliente actualizado correctamente');
      },
      onError: () => {
        toast.error('Revisá los errores en el formulario');
      },
    });
  };

  const handleToggleStatus = (customer: Customer) => {
    if (customer.is_default) {
      toast.warning(
        'El cliente por defecto Consumidor Final no puede ser desactivado.',
      );

      return;
    }

    router.patch(
      toggleStatus.url({ customer: customer.id }),
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          toast.success(
            `Cliente ${customer.is_active ? 'desactivado' : 'activado'} correctamente`,
          );
        },
        onError: (errors) => {
          toast.error(
            errors.customer ?? 'No se pudo cambiar el estado del cliente',
          );
        },
      },
    );
  };

  const handleDeleteConfirm = () => {
    if (!deletingCustomer) {
      return;
    }

    if (deletingCustomer.is_default) {
      toast.warning(
        'El cliente por defecto Consumidor Final no puede ser eliminado.',
      );
      setDeletingCustomer(null);

      return;
    }

    router.delete(destroy.url({ customer: deletingCustomer.id }), {
      preserveScroll: true,
      onSuccess: () => {
        setDeletingCustomer(null);
        toast.success('Cliente eliminado correctamente');
      },
      onError: (errors) => {
        toast.error(
          errors.customer ??
            'No se pudo eliminar el cliente. Si posee operaciones, realizá la baja lógica desactivándolo.',
        );
      },
    });
  };

  const getTaxConditionBadge = (condition: string, label: string) => {
    switch (condition) {
      case 'responsable_inscripto':
        return (
          <Badge className="border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-800 dark:bg-sky-950 dark:text-sky-300">
            {label}
          </Badge>
        );
      case 'monotributo':
        return (
          <Badge className="border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300">
            {label}
          </Badge>
        );
      case 'consumidor_final':
        return (
          <Badge className="border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
            {label}
          </Badge>
        );
      case 'exento':
        return (
          <Badge className="border-purple-200 bg-purple-50 text-purple-800 dark:border-purple-800 dark:bg-purple-950 dark:text-purple-300">
            {label}
          </Badge>
        );
      default:
        return <Badge variant="outline">{label}</Badge>;
    }
  };

  return (
    <>
      <Head title="Clientes" />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <Heading
            title="Clientes"
            description="Padrón centralizado de clientes, condiciones fiscales AFIP y datos de contacto para facturación."
          />
          <Button onClick={handleOpenCreate} className="gap-2">
            <Plus className="h-4 w-4" />
            Nuevo Cliente
          </Button>
        </div>

        {/* Toolbar & Filters */}
        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div className="flex flex-1 flex-col flex-wrap gap-3 sm:flex-row sm:items-center">
            {/* Search Input */}
            <form
              onSubmit={handleSearchSubmit}
              className="relative min-w-[240px] flex-1"
            >
              <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                type="search"
                placeholder="Buscar por nombre, CUIT/DNI, email..."
                className="pl-8"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
              />
            </form>

            {/* Tax Condition Filter */}
            <Select
              value={selectedTaxCondition}
              onValueChange={handleTaxConditionFilterChange}
            >
              <SelectTrigger className="w-full sm:w-[210px]">
                <SelectValue placeholder="Condición fiscal" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todas las condiciones</SelectItem>
                {taxConditions.map((opt) => (
                  <SelectItem key={opt.value} value={opt.value}>
                    {opt.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            {/* Person Type Filter */}
            <Select
              value={selectedPersonType}
              onValueChange={handlePersonTypeFilterChange}
            >
              <SelectTrigger className="w-full sm:w-[170px]">
                <SelectValue placeholder="Tipo de persona" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos los tipos</SelectItem>
                {personTypes.map((opt) => (
                  <SelectItem key={opt.value} value={opt.value}>
                    {opt.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            {/* Status Filter */}
            <Select
              value={selectedStatus}
              onValueChange={handleStatusFilterChange}
            >
              <SelectTrigger className="w-full sm:w-[140px]">
                <SelectValue placeholder="Estado" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos</SelectItem>
                <SelectItem value="active">Activos</SelectItem>
                <SelectItem value="inactive">Inactivos</SelectItem>
              </SelectContent>
            </Select>

            {(searchTerm !== '' ||
              selectedTaxCondition !== 'all' ||
              selectedPersonType !== 'all' ||
              selectedStatus !== 'all') && (
              <Button
                variant="ghost"
                onClick={handleResetFilters}
                className="text-sm text-muted-foreground"
              >
                Limpiar filtros
              </Button>
            )}
          </div>
        </div>

        {/* Clientes Table */}
        <div className="rounded-xl border border-sidebar-border/70 bg-card text-card-foreground shadow-sm dark:border-sidebar-border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-[300px]">Cliente</TableHead>
                <TableHead>Identificación</TableHead>
                <TableHead>Condición Fiscal</TableHead>
                <TableHead>Contacto</TableHead>
                <TableHead className="w-[100px]">Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {customers.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={6}
                    className="h-32 text-center text-muted-foreground"
                  >
                    No se encontraron clientes registrados con los filtros
                    aplicados.
                  </TableCell>
                </TableRow>
              ) : (
                customers.map((customer) => (
                  <TableRow
                    key={customer.id}
                    className={
                      customer.is_default ? 'bg-muted/30 font-medium' : ''
                    }
                  >
                    {/* Cliente */}
                    <TableCell>
                      <div className="flex flex-col gap-1">
                        <div className="flex items-center gap-2">
                          <span className="font-semibold text-foreground">
                            {customer.name}
                          </span>
                          {customer.is_default && (
                            <Badge
                              variant="secondary"
                              className="gap-1 border-emerald-300 bg-emerald-50 text-xs text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                            >
                              <ShieldCheck className="h-3 w-3 text-emerald-600 dark:text-emerald-400" />
                              Por defecto (Mostrador)
                            </Badge>
                          )}
                        </div>
                        <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                          {customer.person_type === 'juridica' ? (
                            <Building2 className="h-3.5 w-3.5" />
                          ) : (
                            <User className="h-3.5 w-3.5" />
                          )}
                          <span>{customer.person_type_label}</span>
                        </div>
                      </div>
                    </TableCell>

                    {/* Identificación */}
                    <TableCell>
                      <div className="flex flex-col gap-1">
                        {customer.id_number ? (
                          <span className="font-mono text-sm font-medium">
                            {customer.id_number}
                          </span>
                        ) : (
                          <span className="text-xs text-muted-foreground italic">
                            Sin identificar
                          </span>
                        )}
                        <span className="text-xs text-muted-foreground">
                          {customer.id_type_label}
                        </span>
                      </div>
                    </TableCell>

                    {/* Condición Fiscal */}
                    <TableCell>
                      {getTaxConditionBadge(
                        customer.tax_condition,
                        customer.tax_condition_label,
                      )}
                    </TableCell>

                    {/* Contacto */}
                    <TableCell>
                      <div className="flex flex-col gap-1 text-xs">
                        {customer.email && (
                          <div className="flex items-center gap-1.5 text-muted-foreground">
                            <Mail className="h-3.5 w-3.5 shrink-0" />
                            <span className="max-w-[200px] truncate">
                              {customer.email}
                            </span>
                          </div>
                        )}
                        {customer.phone && (
                          <div className="flex items-center gap-1.5 text-muted-foreground">
                            <Phone className="h-3.5 w-3.5 shrink-0" />
                            <span>{customer.phone}</span>
                          </div>
                        )}
                        {customer.address && (
                          <div className="flex items-center gap-1.5 text-muted-foreground">
                            <MapPin className="h-3.5 w-3.5 shrink-0" />
                            <span className="max-w-[200px] truncate">
                              {customer.address}
                            </span>
                          </div>
                        )}
                        {!customer.email &&
                          !customer.phone &&
                          !customer.address && (
                            <span className="text-muted-foreground italic">
                              —
                            </span>
                          )}
                      </div>
                    </TableCell>

                    {/* Estado */}
                    <TableCell>
                      <Badge
                        variant={customer.is_active ? 'default' : 'secondary'}
                        className={
                          customer.is_active
                            ? 'bg-emerald-600 text-white hover:bg-emerald-700'
                            : 'text-muted-foreground'
                        }
                      >
                        {customer.is_active ? 'Activo' : 'Inactivo'}
                      </Badge>
                    </TableCell>

                    {/* Acciones */}
                    <TableCell className="text-right">
                      {customer.is_default ? (
                        <span className="pr-2 text-xs text-muted-foreground italic">
                          Protegido
                        </span>
                      ) : (
                        <div className="flex items-center justify-end gap-1">
                          <Button
                            variant="ghost"
                            size="icon"
                            title="Editar cliente"
                            onClick={() => handleOpenEdit(customer)}
                          >
                            <Pencil className="h-4 w-4" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="icon"
                            title={
                              customer.is_active
                                ? 'Desactivar cliente'
                                : 'Activar cliente'
                            }
                            onClick={() => handleToggleStatus(customer)}
                          >
                            <Power
                              className={`h-4 w-4 ${
                                customer.is_active
                                  ? 'text-emerald-600 dark:text-emerald-400'
                                  : 'text-muted-foreground'
                              }`}
                            />
                          </Button>
                          <Button
                            variant="ghost"
                            size="icon"
                            title="Eliminar cliente"
                            className="text-destructive hover:text-destructive"
                            onClick={() => setDeletingCustomer(customer)}
                          >
                            <Trash2 className="h-4 w-4" />
                          </Button>
                        </div>
                      )}
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>
      </div>

      {/* Modal Alta de Cliente */}
      <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
        <DialogContent className="max-h-[90vh] max-w-xl overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Nuevo Cliente</DialogTitle>
            <DialogDescription>
              Completá los datos del cliente. Los Responsables Inscriptos
              requieren CUIT obligatorio.
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handleCreateSubmit} className="flex flex-col gap-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              {/* Tipo de Persona */}
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="create-person-type">Tipo de Persona *</Label>
                <Select
                  value={createForm.data.person_type}
                  onValueChange={handleCreatePersonTypeChange}
                >
                  <SelectTrigger id="create-person-type">
                    <SelectValue placeholder="Seleccionar" />
                  </SelectTrigger>
                  <SelectContent>
                    {personTypes.map((opt) => (
                      <SelectItem key={opt.value} value={opt.value}>
                        {opt.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <InputError message={createForm.errors.person_type} />
              </div>

              {/* Condición Fiscal */}
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="create-tax-condition">Condición Fiscal *</Label>
                <Select
                  value={createForm.data.tax_condition}
                  onValueChange={handleCreateTaxConditionChange}
                >
                  <SelectTrigger id="create-tax-condition">
                    <SelectValue placeholder="Seleccionar" />
                  </SelectTrigger>
                  <SelectContent>
                    {taxConditions.map((opt) => (
                      <SelectItem key={opt.value} value={opt.value}>
                        {opt.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <InputError message={createForm.errors.tax_condition} />
              </div>
            </div>

            {/* Razón Social / Nombre */}
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="create-name">
                {createForm.data.person_type === 'juridica'
                  ? 'Razón Social *'
                  : 'Nombre y Apellido *'}
              </Label>
              <Input
                id="create-name"
                placeholder={
                  createForm.data.person_type === 'juridica'
                    ? 'ej. Distribuidora del Norte S.A.'
                    : 'ej. Gómez Martín Eduardo'
                }
                value={createForm.data.name}
                onChange={(e) => createForm.setData('name', e.target.value)}
              />
              <InputError message={createForm.errors.name} />
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              {/* Tipo de Documento */}
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="create-id-type">Tipo de Documento *</Label>
                <Select
                  value={createForm.data.id_type}
                  disabled={
                    createForm.data.person_type === 'juridica' ||
                    createForm.data.tax_condition !== 'consumidor_final'
                  }
                  onValueChange={(val) => {
                    createForm.setData((prev) => ({
                      ...prev,
                      id_type: val,
                      id_number:
                        val === 'sin_identificar' ? '' : prev.id_number,
                    }));
                  }}
                >
                  <SelectTrigger id="create-id-type">
                    <SelectValue placeholder="Seleccionar" />
                  </SelectTrigger>
                  <SelectContent>
                    {idTypes.map((opt) => (
                      <SelectItem key={opt.value} value={opt.value}>
                        {opt.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <InputError message={createForm.errors.id_type} />
              </div>

              {/* Número de Documento */}
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="create-id-number">
                  Número de Documento{' '}
                  {createForm.data.id_type === 'sin_identificar'
                    ? '(Opcional)'
                    : '*'}
                </Label>
                <Input
                  id="create-id-number"
                  disabled={createForm.data.id_type === 'sin_identificar'}
                  placeholder={
                    createForm.data.id_type === 'cuit'
                      ? 'ej. 30-50085862-8 o 30500858628'
                      : createForm.data.id_type === 'dni'
                        ? 'ej. 35123456'
                        : 'No aplica para sin identificar'
                  }
                  value={createForm.data.id_number}
                  onChange={(e) =>
                    createForm.setData('id_number', e.target.value)
                  }
                />
                <InputError message={createForm.errors.id_number} />
              </div>
            </div>

            {/* Domicilio */}
            <div className="flex flex-col gap-1.5">
              <Label htmlFor="create-address">Domicilio</Label>
              <Input
                id="create-address"
                placeholder="ej. Av. San Martín 1234"
                value={createForm.data.address}
                onChange={(e) => createForm.setData('address', e.target.value)}
              />
              <InputError message={createForm.errors.address} />
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              {/* Teléfono */}
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="create-phone">Teléfono</Label>
                <Input
                  id="create-phone"
                  placeholder="ej. +54 387 15-456-7890"
                  value={createForm.data.phone}
                  onChange={(e) => createForm.setData('phone', e.target.value)}
                />
                <InputError message={createForm.errors.phone} />
              </div>

              {/* Email */}
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="create-email">Correo Electrónico</Label>
                <Input
                  id="create-email"
                  type="email"
                  placeholder="ej. cliente@correo.com"
                  value={createForm.data.email}
                  onChange={(e) => createForm.setData('email', e.target.value)}
                />
                <InputError message={createForm.errors.email} />
              </div>
            </div>

            {/* Activo Checkbox */}
            <div className="flex items-center gap-2 pt-2">
              <Checkbox
                id="create-is-active"
                checked={createForm.data.is_active}
                onCheckedChange={(checked) =>
                  createForm.setData('is_active', Boolean(checked))
                }
              />
              <Label
                htmlFor="create-is-active"
                className="cursor-pointer font-normal"
              >
                Cliente activo para facturación
              </Label>
            </div>

            <DialogFooter className="pt-4">
              <Button
                type="button"
                variant="outline"
                onClick={() => setIsCreateOpen(false)}
              >
                Cancelar
              </Button>
              <Button type="submit" disabled={createForm.processing}>
                {createForm.processing ? 'Registrando...' : 'Registrar Cliente'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Modal Edición de Cliente */}
      <Dialog
        open={Boolean(editingCustomer)}
        onOpenChange={(open) => !open && setEditingCustomer(null)}
      >
        <DialogContent className="max-h-[90vh] max-w-xl overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Editar Cliente</DialogTitle>
            <DialogDescription>
              Modificá los datos del cliente. Si ya posee operaciones
              vinculadas, su documento y condición fiscal quedarán protegidos.
            </DialogDescription>
          </DialogHeader>

          {editingCustomer && (
            <form onSubmit={handleEditSubmit} className="flex flex-col gap-4">
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                {/* Tipo de Persona */}
                <div className="flex flex-col gap-1.5">
                  <Label htmlFor="edit-person-type">Tipo de Persona *</Label>
                  <Select
                    value={editForm.data.person_type}
                    onValueChange={handleEditPersonTypeChange}
                  >
                    <SelectTrigger id="edit-person-type">
                      <SelectValue placeholder="Seleccionar" />
                    </SelectTrigger>
                    <SelectContent>
                      {personTypes.map((opt) => (
                        <SelectItem key={opt.value} value={opt.value}>
                          {opt.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <InputError message={editForm.errors.person_type} />
                </div>

                {/* Condición Fiscal */}
                <div className="flex flex-col gap-1.5">
                  <Label htmlFor="edit-tax-condition">Condición Fiscal *</Label>
                  <Select
                    value={editForm.data.tax_condition}
                    disabled={editingCustomer.has_associated_records}
                    onValueChange={handleEditTaxConditionChange}
                  >
                    <SelectTrigger id="edit-tax-condition">
                      <SelectValue placeholder="Seleccionar" />
                    </SelectTrigger>
                    <SelectContent>
                      {taxConditions.map((opt) => (
                        <SelectItem key={opt.value} value={opt.value}>
                          {opt.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {editingCustomer.has_associated_records && (
                    <span className="text-xs text-amber-600 dark:text-amber-400">
                      Protegido por operaciones registradas.
                    </span>
                  )}
                  <InputError message={editForm.errors.tax_condition} />
                </div>
              </div>

              {/* Razón Social / Nombre */}
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="edit-name">
                  {editForm.data.person_type === 'juridica'
                    ? 'Razón Social *'
                    : 'Nombre y Apellido *'}
                </Label>
                <Input
                  id="edit-name"
                  value={editForm.data.name}
                  onChange={(e) => editForm.setData('name', e.target.value)}
                />
                <InputError message={editForm.errors.name} />
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                {/* Tipo de Documento */}
                <div className="flex flex-col gap-1.5">
                  <Label htmlFor="edit-id-type">Tipo de Documento *</Label>
                  <Select
                    value={editForm.data.id_type}
                    disabled={
                      editingCustomer.has_associated_records ||
                      editForm.data.person_type === 'juridica' ||
                      editForm.data.tax_condition !== 'consumidor_final'
                    }
                    onValueChange={(val) => {
                      editForm.setData((prev) => ({
                        ...prev,
                        id_type: val,
                        id_number:
                          val === 'sin_identificar' ? '' : prev.id_number,
                      }));
                    }}
                  >
                    <SelectTrigger id="edit-id-type">
                      <SelectValue placeholder="Seleccionar" />
                    </SelectTrigger>
                    <SelectContent>
                      {idTypes.map((opt) => (
                        <SelectItem key={opt.value} value={opt.value}>
                          {opt.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <InputError message={editForm.errors.id_type} />
                </div>

                {/* Número de Documento */}
                <div className="flex flex-col gap-1.5">
                  <Label htmlFor="edit-id-number">
                    Número de Documento{' '}
                    {editForm.data.id_type === 'sin_identificar'
                      ? '(Opcional)'
                      : '*'}
                  </Label>
                  <Input
                    id="edit-id-number"
                    disabled={
                      editingCustomer.has_associated_records ||
                      editForm.data.id_type === 'sin_identificar'
                    }
                    value={editForm.data.id_number}
                    onChange={(e) =>
                      editForm.setData('id_number', e.target.value)
                    }
                  />
                  {editingCustomer.has_associated_records && (
                    <span className="text-xs text-amber-600 dark:text-amber-400">
                      Protegido por operaciones registradas.
                    </span>
                  )}
                  <InputError message={editForm.errors.id_number} />
                </div>
              </div>

              {/* Domicilio */}
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="edit-address">Domicilio</Label>
                <Input
                  id="edit-address"
                  value={editForm.data.address}
                  onChange={(e) => editForm.setData('address', e.target.value)}
                />
                <InputError message={editForm.errors.address} />
              </div>

              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                {/* Teléfono */}
                <div className="flex flex-col gap-1.5">
                  <Label htmlFor="edit-phone">Teléfono</Label>
                  <Input
                    id="edit-phone"
                    value={editForm.data.phone}
                    onChange={(e) => editForm.setData('phone', e.target.value)}
                  />
                  <InputError message={editForm.errors.phone} />
                </div>

                {/* Email */}
                <div className="flex flex-col gap-1.5">
                  <Label htmlFor="edit-email">Correo Electrónico</Label>
                  <Input
                    id="edit-email"
                    type="email"
                    value={editForm.data.email}
                    onChange={(e) => editForm.setData('email', e.target.value)}
                  />
                  <InputError message={editForm.errors.email} />
                </div>
              </div>

              {/* Activo Checkbox */}
              <div className="flex items-center gap-2 pt-2">
                <Checkbox
                  id="edit-is-active"
                  checked={editForm.data.is_active}
                  onCheckedChange={(checked) =>
                    editForm.setData('is_active', Boolean(checked))
                  }
                />
                <Label
                  htmlFor="edit-is-active"
                  className="cursor-pointer font-normal"
                >
                  Cliente activo para facturación
                </Label>
              </div>

              <DialogFooter className="pt-4">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setEditingCustomer(null)}
                >
                  Cancelar
                </Button>
                <Button type="submit" disabled={editForm.processing}>
                  {editForm.processing ? 'Guardando...' : 'Guardar Cambios'}
                </Button>
              </DialogFooter>
            </form>
          )}
        </DialogContent>
      </Dialog>

      {/* Diálogo Confirmación Eliminación */}
      <Dialog
        open={Boolean(deletingCustomer)}
        onOpenChange={(open) => !open && setDeletingCustomer(null)}
      >
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Eliminar Cliente</DialogTitle>
            <DialogDescription>
              {deletingCustomer?.has_associated_records ? (
                <span className="font-medium text-destructive">
                  Este cliente posee comprobantes o ventas vinculadas. No se
                  puede eliminar físicamente; debés realizar la baja lógica
                  desactivándolo.
                </span>
              ) : (
                <span>
                  ¿Estás seguro de que deseás eliminar permanentemente a{' '}
                  <strong className="text-foreground">
                    {deletingCustomer?.name}
                  </strong>
                  ? Esta acción no se puede deshacer.
                </span>
              )}
            </DialogDescription>
          </DialogHeader>

          <DialogFooter className="gap-2 sm:gap-0">
            <Button
              type="button"
              variant="outline"
              onClick={() => setDeletingCustomer(null)}
            >
              Cancelar
            </Button>
            {!deletingCustomer?.has_associated_records && (
              <Button
                type="button"
                variant="destructive"
                onClick={handleDeleteConfirm}
              >
                Eliminar Cliente
              </Button>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

CustomersIndex.layout = {
  breadcrumbs: [
    {
      title: 'Dashboard',
      href: dashboard(),
    },
    {
      title: 'Clientes',
      href: index(),
    },
  ] satisfies BreadcrumbItem[],
};
