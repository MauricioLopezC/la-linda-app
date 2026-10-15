import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
  title: string;
  href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
  title: string;
  href: NonNullable<InertiaLinkProps['href']>;
  icon?: LucideIcon | null;
  isActive?: boolean;
  /** Module Gate required to show the item (HU-004); items without one are always shown. */
  permission?: App.Enums.Security.Permission;
};

export type NavGroup = {
  label: string;
  items: NavItem[];
};
