import { usePage } from '@inertiajs/react';

export type CanFn = (permission: App.Enums.Security.Permission) => boolean;

/**
 * Checks the module Gates shared in `auth.can` (HU-004), to hide links and actions the server
 * would answer with 403.
 */
export function useCan(): CanFn {
  const { auth } = usePage().props;

  return (permission) => Boolean(auth?.can?.[permission]);
}
