import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
  Check,
  Copy,
  KeyRound,
  Pencil,
  Plus,
  Power,
  Search,
  ShieldAlert,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import TablePagination from '@/components/table-pagination';
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
import { panel } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type UserItem = App.Data.Security.UserData;
type BranchItem = App.Data.Organization.BranchData;

type RoleOption = {
  value: string;
  label: string;
};

type PaginationLink = {
  url: string | null;
  label: string;
  active: boolean;
};

type PaginatedUsers = {
  data: UserItem[];
  links: PaginationLink[];
  current_page: number;
  last_page: number;
  per_page: number;
  from: number | null;
  to: number | null;
  total: number;
};

type Props = {
  users: PaginatedUsers;
  branches: BranchItem[];
  roles: RoleOption[];
  filters: {
    search: string;
    role: string;
    status: string;
  };
};

export default function UsersIndex({
  users,
  branches = [],
  roles = [],
  filters,
}: Props) {
  const page = usePage();
  const flashPassword = page.flash?.generated_password ?? null;

  const [searchTerm, setSearchTerm] = useState(filters.search ?? '');
  const [selectedRole, setSelectedRole] = useState(filters.role ?? 'all');
  const [selectedStatus, setSelectedStatus] = useState(filters.status ?? 'all');

  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [editingUser, setEditingUser] = useState<UserItem | null>(null);
  const [resettingUser, setResettingUser] = useState<UserItem | null>(null);
  const [togglingUser, setTogglingUser] = useState<UserItem | null>(null);

  const [dismissedPassword, setDismissedPassword] = useState<string | null>(
    null,
  );
  const displayedPassword =
    flashPassword && flashPassword !== dismissedPassword ? flashPassword : null;
  const [copied, setCopied] = useState(false);

  const createForm = useForm({
    name: '',
    email: '',
    role: roles[0]?.value ?? 'cajero',
    branch_id: '',
    password: '',
  });

  const editForm = useForm({
    name: '',
    email: '',
    role: '',
    branch_id: '',
  });

  const resetForm = useForm({
    password: '',
  });

  const applyFilters = (
    newSearch: string,
    newRole: string,
    newStatus: string,
  ) => {
    router.get(
      '/settings/users',
      {
        search: newSearch || undefined,
        role: newRole !== 'all' ? newRole : undefined,
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
    applyFilters(searchTerm, selectedRole, selectedStatus);
  };

  const handleRoleChange = (role: string) => {
    setSelectedRole(role);
    applyFilters(searchTerm, role, selectedStatus);
  };

  const handleStatusChange = (status: string) => {
    setSelectedStatus(status);
    applyFilters(searchTerm, selectedRole, status);
  };

  const handleOpenCreate = () => {
    createForm.reset();
    createForm.clearErrors();
    setIsCreateOpen(true);
  };

  const handleCreateSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    setDismissedPassword(null);
    createForm.post('/settings/users', {
      preserveScroll: true,
      onSuccess: () => {
        setIsCreateOpen(false);
        createForm.reset();
        toast.success('Usuario creado correctamente');
      },
      onError: () => {
        toast.error('Hubo un error al crear el usuario');
      },
    });
  };

  const handleOpenEdit = (user: UserItem) => {
    setEditingUser(user);
    editForm.setData({
      name: user.name,
      email: user.email,
      role: user.role,
      branch_id: user.branch_id ? String(user.branch_id) : '',
    });
    editForm.clearErrors();
  };

  const handleEditSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!editingUser) {
      return;
    }

    editForm.put(`/settings/users/${editingUser.id}`, {
      preserveScroll: true,
      onSuccess: () => {
        setEditingUser(null);
        toast.success('Usuario actualizado correctamente');
      },
      onError: () => {
        toast.error('Error al actualizar el usuario');
      },
    });
  };

  const handleToggleConfirm = () => {
    if (!togglingUser) {
      return;
    }

    router.patch(
      `/settings/users/${togglingUser.id}/toggle-active`,
      {},
      {
        preserveScroll: true,
        onSuccess: () => {
          const actionText = togglingUser.is_active
            ? 'desactivado'
            : 'activado';
          toast.success(`Usuario ${actionText} correctamente`);
          setTogglingUser(null);
        },
        onError: (errors) => {
          toast.error(
            errors.user || 'No se pudo cambiar el estado del usuario.',
          );
        },
      },
    );
  };

  const handleOpenReset = (user: UserItem) => {
    setResettingUser(user);
    resetForm.reset();
    resetForm.clearErrors();
  };

  const handleResetSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!resettingUser) {
      return;
    }

    setDismissedPassword(null);
    resetForm.post(`/settings/users/${resettingUser.id}/reset-password`, {
      preserveScroll: true,
      onSuccess: () => {
        setResettingUser(null);
        toast.success('Contraseña restablecida correctamente');
      },
      onError: () => {
        toast.error('Error al restablecer la contraseña');
      },
    });
  };

  const handleCopyPassword = () => {
    if (displayedPassword) {
      navigator.clipboard.writeText(displayedPassword);
      setCopied(true);
      toast.success('Contraseña copiada al portapapeles');
      setTimeout(() => setCopied(false), 2000);
    }
  };

  return (
    <>
      <Head title="Usuarios" />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <Heading
            title="Usuarios del sistema"
            description="Administrá el personal interno de La Linda, sus roles y accesos."
          />
          <Button onClick={handleOpenCreate}>
            <Plus className="mr-1.5 size-4" />
            Nuevo Usuario
          </Button>
        </div>

        {/* Barra de Filtros */}
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <form
            onSubmit={handleSearchSubmit}
            className="relative max-w-sm flex-1"
          >
            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
              placeholder="Buscar por nombre o correo..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="pl-9"
            />
          </form>

          <div className="flex flex-wrap items-center gap-2">
            <Select value={selectedRole} onValueChange={handleRoleChange}>
              <SelectTrigger className="w-[180px]">
                <SelectValue placeholder="Rol" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos los roles</SelectItem>
                {roles.map((r) => (
                  <SelectItem key={r.value} value={r.value}>
                    {r.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Select value={selectedStatus} onValueChange={handleStatusChange}>
              <SelectTrigger className="w-[150px]">
                <SelectValue placeholder="Estado" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos los estados</SelectItem>
                <SelectItem value="active">Activos</SelectItem>
                <SelectItem value="inactive">Inactivos</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        {/* Tabla de Usuarios */}
        <div className="rounded-md border bg-card">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nombre</TableHead>
                <TableHead>Correo electrónico</TableHead>
                <TableHead>Rol asignado</TableHead>
                <TableHead>Sucursal</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {users.data.length === 0 ? (
                <TableRow>
                  <TableCell
                    colSpan={6}
                    className="h-24 text-center text-muted-foreground"
                  >
                    No se encontraron usuarios que coincidan con los filtros.
                  </TableCell>
                </TableRow>
              ) : (
                users.data.map((user) => (
                  <TableRow key={user.id}>
                    <TableCell className="font-medium">{user.name}</TableCell>
                    <TableCell className="text-muted-foreground">
                      {user.email}
                    </TableCell>
                    <TableCell>
                      <Badge variant="outline" className="font-normal">
                        {user.role_label}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      {user.branch_name ? (
                        <span>{user.branch_name}</span>
                      ) : (
                        <span className="text-xs text-muted-foreground italic">
                          Sin asignar
                        </span>
                      )}
                    </TableCell>
                    <TableCell>
                      <Badge
                        variant={user.is_active ? 'default' : 'secondary'}
                        className={
                          user.is_active
                            ? 'bg-emerald-600/15 text-emerald-700 hover:bg-emerald-600/25 dark:text-emerald-400'
                            : 'bg-muted text-muted-foreground'
                        }
                      >
                        {user.is_active ? 'Activo' : 'Inactivo'}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex items-center justify-end gap-1">
                        <Button
                          variant="ghost"
                          size="icon"
                          title="Editar usuario"
                          onClick={() => handleOpenEdit(user)}
                        >
                          <Pencil className="size-4" />
                          <span className="sr-only">Editar</span>
                        </Button>

                        <Button
                          variant="ghost"
                          size="icon"
                          title="Restablecer contraseña"
                          onClick={() => handleOpenReset(user)}
                        >
                          <KeyRound className="size-4 text-amber-600 dark:text-amber-400" />
                          <span className="sr-only">
                            Restablecer contraseña
                          </span>
                        </Button>

                        <Button
                          variant="ghost"
                          size="icon"
                          title={
                            user.is_active
                              ? 'Desactivar usuario'
                              : 'Activar usuario'
                          }
                          onClick={() => setTogglingUser(user)}
                          className={
                            user.is_active
                              ? 'text-destructive hover:bg-destructive/10 hover:text-destructive'
                              : 'text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700 dark:text-emerald-400'
                          }
                        >
                          <Power className="size-4" />
                          <span className="sr-only">
                            {user.is_active ? 'Desactivar' : 'Activar'}
                          </span>
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>

        {/* Paginación */}
        <TablePagination
          currentPage={users.current_page}
          totalPages={users.last_page}
          totalItems={users.total}
          pageSize={users.per_page ?? 15}
          onPageChange={(page) => {
            router.get(
              '/settings/users',
              {
                search: filters.search || undefined,
                role: filters.role !== 'all' ? filters.role : undefined,
                status: filters.status !== 'all' ? filters.status : undefined,
                page,
              },
              { preserveState: true, preserveScroll: true },
            );
          }}
          entityName="usuarios"
          className="mt-4"
        />
      </div>

      {/* Modal: Crear Usuario */}
      <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
        <DialogContent className="sm:max-w-[480px]">
          <form onSubmit={handleCreateSubmit} className="space-y-4">
            <DialogHeader>
              <DialogTitle>Nuevo Usuario</DialogTitle>
              <DialogDescription>
                Registrá un empleado en el sistema. Podés definir una clave o
                dejarla vacía para generar una aleatoria segura.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-3">
              <div className="space-y-1.5">
                <Label htmlFor="create-name">Nombre y apellido *</Label>
                <Input
                  id="create-name"
                  placeholder="Ej. Juan Pérez"
                  value={createForm.data.name}
                  onChange={(e) => createForm.setData('name', e.target.value)}
                  required
                />
                <InputError message={createForm.errors.name} />
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="create-email">Correo electrónico *</Label>
                <Input
                  id="create-email"
                  type="email"
                  placeholder="empleado@lalinda.com"
                  value={createForm.data.email}
                  onChange={(e) => createForm.setData('email', e.target.value)}
                  required
                />
                <InputError message={createForm.errors.email} />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1.5">
                  <Label htmlFor="create-role">Rol asignado *</Label>
                  <Select
                    value={createForm.data.role}
                    onValueChange={(val) => createForm.setData('role', val)}
                  >
                    <SelectTrigger id="create-role">
                      <SelectValue placeholder="Seleccionar rol" />
                    </SelectTrigger>
                    <SelectContent>
                      {roles.map((r) => (
                        <SelectItem key={r.value} value={r.value}>
                          {r.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <InputError message={createForm.errors.role} />
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="create-branch">Sucursal opcional</Label>
                  <Select
                    value={createForm.data.branch_id || 'none'}
                    onValueChange={(val) =>
                      createForm.setData('branch_id', val === 'none' ? '' : val)
                    }
                  >
                    <SelectTrigger id="create-branch">
                      <SelectValue placeholder="Sin asignar" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="none">Sin asignar</SelectItem>
                      {branches.map((b) => (
                        <SelectItem key={b.id} value={String(b.id)}>
                          {b.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <InputError message={createForm.errors.branch_id} />
                </div>
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="create-password">Contraseña inicial</Label>
                <Input
                  id="create-password"
                  type="text"
                  placeholder="Dejar vacío para generar clave aleatoria segura"
                  value={createForm.data.password}
                  onChange={(e) =>
                    createForm.setData('password', e.target.value)
                  }
                />
                <p className="text-xs text-muted-foreground">
                  Si no ingresás una contraseña, el sistema generará una segura
                  y la mostrará en pantalla al guardar.
                </p>
                <InputError message={createForm.errors.password} />
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
                Crear Usuario
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Modal: Editar Usuario */}
      <Dialog
        open={Boolean(editingUser)}
        onOpenChange={(open) => !open && setEditingUser(null)}
      >
        <DialogContent className="sm:max-w-[480px]">
          <form onSubmit={handleEditSubmit} className="space-y-4">
            <DialogHeader>
              <DialogTitle>Editar Usuario</DialogTitle>
              <DialogDescription>
                Modificá los datos del empleado, su rol o su sucursal
                predeterminada.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-3">
              <div className="space-y-1.5">
                <Label htmlFor="edit-name">Nombre y apellido *</Label>
                <Input
                  id="edit-name"
                  value={editForm.data.name}
                  onChange={(e) => editForm.setData('name', e.target.value)}
                  required
                />
                <InputError message={editForm.errors.name} />
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="edit-email">Correo electrónico *</Label>
                <Input
                  id="edit-email"
                  type="email"
                  value={editForm.data.email}
                  onChange={(e) => editForm.setData('email', e.target.value)}
                  required
                />
                <InputError message={editForm.errors.email} />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1.5">
                  <Label htmlFor="edit-role">Rol asignado *</Label>
                  <Select
                    value={editForm.data.role}
                    onValueChange={(val) => editForm.setData('role', val)}
                  >
                    <SelectTrigger id="edit-role">
                      <SelectValue placeholder="Seleccionar rol" />
                    </SelectTrigger>
                    <SelectContent>
                      {roles.map((r) => (
                        <SelectItem key={r.value} value={r.value}>
                          {r.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <InputError message={editForm.errors.role} />
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="edit-branch">Sucursal</Label>
                  <Select
                    value={editForm.data.branch_id || 'none'}
                    onValueChange={(val) =>
                      editForm.setData('branch_id', val === 'none' ? '' : val)
                    }
                  >
                    <SelectTrigger id="edit-branch">
                      <SelectValue placeholder="Sin asignar" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="none">Sin asignar</SelectItem>
                      {branches.map((b) => (
                        <SelectItem key={b.id} value={String(b.id)}>
                          {b.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <InputError message={editForm.errors.branch_id} />
                </div>
              </div>
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setEditingUser(null)}
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

      {/* Modal: Confirmación de Toggle Active (Baja / Activación lógica) */}
      <Dialog
        open={Boolean(togglingUser)}
        onOpenChange={(open) => !open && setTogglingUser(null)}
      >
        <DialogContent className="sm:max-w-[420px]">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <ShieldAlert className="size-5 text-amber-600 dark:text-amber-400" />
              {togglingUser?.is_active
                ? '¿Desactivar usuario?'
                : '¿Reactivar usuario?'}
            </DialogTitle>
            <DialogDescription>
              {togglingUser?.is_active
                ? `Al desactivar a "${togglingUser?.name}", no podrá iniciar sesión y cualquier sesión activa será cerrada de inmediato.`
                : `¿Confirmás reactivar a "${togglingUser?.name}" para permitirle volver a operar el sistema?`}
            </DialogDescription>
          </DialogHeader>

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => setTogglingUser(null)}
            >
              Cancelar
            </Button>
            <Button
              variant={togglingUser?.is_active ? 'destructive' : 'default'}
              onClick={handleToggleConfirm}
            >
              {togglingUser?.is_active
                ? 'Desactivar usuario'
                : 'Reactivar usuario'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Modal: Restablecer Contraseña */}
      <Dialog
        open={Boolean(resettingUser)}
        onOpenChange={(open) => !open && setResettingUser(null)}
      >
        <DialogContent className="sm:max-w-[440px]">
          <form onSubmit={handleResetSubmit} className="space-y-4">
            <DialogHeader>
              <DialogTitle>Restablecer Contraseña</DialogTitle>
              <DialogDescription>
                Restablecer la clave para <strong>{resettingUser?.name}</strong>
                . Podés ingresar una nueva clave o dejarla vacía para generar
                una aleatoria segura.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-1.5">
              <Label htmlFor="reset-password">Nueva contraseña</Label>
              <Input
                id="reset-password"
                type="text"
                placeholder="Dejar vacío para generar clave aleatoria segura"
                value={resetForm.data.password}
                onChange={(e) => resetForm.setData('password', e.target.value)}
              />
              <InputError message={resetForm.errors.password} />
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => setResettingUser(null)}
              >
                Cancelar
              </Button>
              <Button type="submit" disabled={resetForm.processing}>
                Restablecer Clave
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Modal: Mostrar Contraseña Generada por única vez */}
      <Dialog
        open={Boolean(displayedPassword)}
        onOpenChange={(open) => {
          if (!open && flashPassword) {
            setDismissedPassword(flashPassword);
          }
        }}
      >
        <DialogContent className="sm:max-w-[440px]">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
              <KeyRound className="size-5" />
              Contraseña generada
            </DialogTitle>
            <DialogDescription>
              Esta contraseña se muestra <strong>por única vez</strong>.
              Asegurate de copiarla y entregársela al empleado de forma segura.
            </DialogDescription>
          </DialogHeader>

          <div className="my-2 rounded-lg border bg-muted/50 p-4">
            <div className="flex items-center justify-between gap-2">
              <code className="font-mono text-lg font-bold tracking-wider text-primary select-all">
                {displayedPassword}
              </code>
              <Button
                type="button"
                variant="secondary"
                size="sm"
                onClick={handleCopyPassword}
                className="gap-1.5"
              >
                {copied ? (
                  <>
                    <Check className="size-4 text-emerald-600" />
                    Copiado
                  </>
                ) : (
                  <>
                    <Copy className="size-4" />
                    Copiar
                  </>
                )}
              </Button>
            </div>
          </div>

          <DialogFooter>
            <Button
              type="button"
              onClick={() => {
                if (flashPassword) {
                  setDismissedPassword(flashPassword);
                }
              }}
              className="w-full"
            >
              Entendido, ya la copié
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

UsersIndex.layout = {
  breadcrumbs: [
    {
      title: 'Inicio',
      href: panel(),
    },
    {
      title: 'Configuración',
      href: '/settings/profile',
    },
    {
      title: 'Usuarios',
      href: '/settings/users',
    },
  ] satisfies BreadcrumbItem[],
};
