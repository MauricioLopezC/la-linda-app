import { cn } from '@/lib/utils';

type Props = {
  label: string;
  value: string;
  hint?: string;
  valueClassName?: string;
};

export default function DashboardKpiCard({
  label,
  value,
  hint,
  valueClassName,
}: Props) {
  return (
    <div className="space-y-1 rounded-lg border border-sidebar-border/70 p-4">
      <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
        {label}
      </p>
      <p
        className={cn(
          'text-2xl font-bold tracking-tight text-foreground',
          valueClassName,
        )}
      >
        {value}
      </p>
      {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
    </div>
  );
}
