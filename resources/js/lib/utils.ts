import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
  return typeof url === 'string' ? url : url.url;
}

export function formatCurrency(amount: number | string): string {
  return Number(amount).toLocaleString('es-AR', {
    style: 'currency',
    currency: 'ARS',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

/**
 * Formats a stock quantity without trailing zeros: whole numbers for discrete
 * units (e.g. "Unidad"), up to 2 decimals otherwise.
 */
export function formatStockQuantity(
  quantity: number,
  unitName: string,
): string {
  const isDiscrete =
    unitName.toLowerCase().includes('unidad') ||
    unitName.toLowerCase() === 'u' ||
    unitName.toLowerCase() === 'un' ||
    Number.isInteger(quantity);

  return quantity.toLocaleString('es-AR', {
    minimumFractionDigits: 0,
    maximumFractionDigits: isDiscrete ? 0 : 2,
  });
}
