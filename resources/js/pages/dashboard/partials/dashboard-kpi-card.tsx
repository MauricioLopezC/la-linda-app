type Props = {
  label: string;
  value: string;
  hint?: string;
};

export default function DashboardKpiCard({ label, value, hint }: Props) {
  return (
    <div className="space-y-1 rounded-lg border border-sidebar-border/70 p-4">
      <p className="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
        {label}
      </p>
      <p className="text-2xl font-bold tracking-tight text-foreground">
        {value}
      </p>
      {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
    </div>
  );
}
