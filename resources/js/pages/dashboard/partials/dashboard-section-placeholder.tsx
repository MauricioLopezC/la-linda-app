import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

type Props = {
  title: string;
  description: string;
  story: string;
};

export default function DashboardSectionPlaceholder({
  title,
  description,
  story,
}: Props) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        <CardDescription>{description}</CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          {Array.from({ length: 4 }).map((_, position) => (
            <Skeleton key={position} className="h-20 w-full" />
          ))}
        </div>
        <p className="text-sm text-muted-foreground">Disponible en {story}.</p>
      </CardContent>
    </Card>
  );
}
