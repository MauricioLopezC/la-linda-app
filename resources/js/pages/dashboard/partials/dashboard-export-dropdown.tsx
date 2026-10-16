import { Download, FileSpreadsheet, FileText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { csv, excel } from '@/routes/dashboard/export';

type Props = {
  filters: App.Data.Reporting.DashboardFiltersData;
};

export default function DashboardExportDropdown({ filters }: Props) {
  const query = {
    date_from: filters.date_from,
    date_to: filters.date_to,
    branch_id: filters.branch_id ?? undefined,
    channel: filters.channel ?? undefined,
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="outline" className="gap-1.5">
          <Download className="size-4" />
          Exportar
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem asChild>
          <a
            href={csv.url({ query })}
            download
            className="flex cursor-pointer items-center gap-2"
          >
            <FileText className="size-4 text-muted-foreground" />
            Exportar a CSV
          </a>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <a
            href={excel.url({ query })}
            download
            className="flex cursor-pointer items-center gap-2"
          >
            <FileSpreadsheet className="size-4 text-muted-foreground" />
            Exportar a Excel (.xlsx)
          </a>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
