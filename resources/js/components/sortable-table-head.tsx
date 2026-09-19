import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import React from 'react';
import { TableHead } from '@/components/ui/table';
import { cn } from '@/lib/utils';

type Props = {
  column: string;
  label?: string;
  children?: React.ReactNode;
  currentColumn: string;
  direction?: 'asc' | 'desc';
  currentDirection?: 'asc' | 'desc';
  onSort: (column: string) => void;
  className?: string;
  align?: 'left' | 'right' | 'center';
};

export default function SortableTableHead({
  column,
  label,
  children,
  currentColumn,
  direction,
  currentDirection,
  onSort,
  className,
  align = 'left',
}: Props) {
  const isActive = currentColumn === column;
  const activeDirection = direction ?? currentDirection ?? 'asc';
  const content = children ?? label;
  const accessibleName =
    label ?? (typeof children === 'string' ? children : column);

  return (
    <TableHead
      className={cn(
        align === 'right' && 'text-right',
        align === 'center' && 'text-center',
        className,
      )}
    >
      <button
        type="button"
        onClick={() => onSort(column)}
        className={cn(
          '-mx-1 inline-flex items-center gap-1.5 rounded px-1 py-0.5 font-medium transition-colors hover:text-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none',
          isActive ? 'font-semibold text-foreground' : 'text-muted-foreground',
          align === 'right' && 'ml-auto flex-row-reverse',
        )}
        aria-label={`Ordenar por ${accessibleName} ${isActive && activeDirection === 'asc' ? 'descendente' : 'ascendente'}`}
      >
        <span>{content}</span>
        {isActive ? (
          activeDirection === 'asc' ? (
            <ArrowUp className="size-3.5 text-primary" />
          ) : (
            <ArrowDown className="size-3.5 text-primary" />
          )
        ) : (
          <ArrowUpDown className="size-3 opacity-40 hover:opacity-100" />
        )}
      </button>
    </TableHead>
  );
}
