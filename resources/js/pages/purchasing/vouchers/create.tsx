import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Loader2, Plus, Search, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { store } from '@/actions/App/Http/Controllers/Purchasing/SupplierVoucherController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatCurrency } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
  articles as searchArticles,
  index,
} from '@/routes/purchasing/vouchers';
import type { BreadcrumbItem } from '@/types';

type Supplier = App.Data.Purchasing.SupplierOptionData;
type Option = App.Data.Purchasing.SupplierVoucherOptionData;
type Article = App.Data.Purchasing.PurchaseOrderArticleOptionData;

type VoucherItemForm = {
  article_id: string | null;
  article_label: string | null;
  description: string;
  quantity: string;
  unit_of_measure: string;
  unit_price: string;
  line_total: string;
};

type VoucherFormData = {
  supplier_id: string;
  type: string;
  letter: string;
  point_of_sale: string;
  number: string;
  issue_date: string;
  due_date: string;
  total_amount: string;
  notes: string;
  items: VoucherItemForm[];
};

type Props = {
  suppliers: Supplier[];
  voucherTypes: Option[];
  letters: Option[];
  today: string;
};

const emptyItem = (): VoucherItemForm => ({
  article_id: null,
  article_label: null,
  description: '',
  quantity: '1',
  unit_of_measure: '',
  unit_price: '',
  line_total: '',
});

function formatDecimalInput(value: string, decimals: number): string {
  const sanitized = value.replace(/[^\d,]/g, '');
  const [integerPart = '', ...decimalParts] = sanitized.split(',');
  const decimalDigits = decimalParts.join('').slice(0, decimals);

  return sanitized.includes(',')
    ? `${integerPart},${decimalDigits}`
    : integerPart;
}

function completeDecimalInput(value: string, decimals: number): string {
  if (value === '') {
    return '';
  }

  const [integerPart = '0', decimalPart = ''] = formatDecimalInput(
    value,
    decimals,
  ).split(',');

  return `${integerPart || '0'},${decimalPart.padEnd(decimals, '0')}`;
}

function argentineMoneyValue(value: string): number {
  return Number(canonicalMoney(value)) || 0;
}

function formatArgentineMoneyInput(value: string): string {
  const sanitized = value.replace(/[^\d,]/g, '');

  if (sanitized === '') {
    return '';
  }

  const hasDecimalSeparator = sanitized.includes(',');
  const [integerPart = '', ...decimalParts] = sanitized.split(',');
  const integerDigits = integerPart.replace(/^0+(?=\d)/, '') || '0';
  const groupedInteger = integerDigits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  const decimalDigits = decimalParts.join('').slice(0, 2);

  return hasDecimalSeparator
    ? `${groupedInteger},${decimalDigits}`
    : groupedInteger;
}

function completeArgentineMoney(value: string): string {
  if (value === '') {
    return '';
  }

  const [integerPart, decimalPart = ''] =
    formatArgentineMoneyInput(value).split(',');

  return `${integerPart},${decimalPart.padEnd(2, '0')}`;
}

function canonicalMoney(value: string): string {
  return value.replaceAll('.', '').replace(',', '.');
}

function onlyDigits(value: string, maximumLength: number): string {
  return value.replace(/\D/g, '').slice(0, maximumLength);
}

export default function CreateSupplierVoucher({
  suppliers,
  voucherTypes,
  letters,
  today,
}: Props) {
  const form = useForm<VoucherFormData>({
    supplier_id: '',
    type: 'factura',
    letter: 'A',
    point_of_sale: '',
    number: '',
    issue_date: today,
    due_date: '',
    total_amount: '',
    notes: '',
    items: [emptyItem()],
  });

  const errors = form.errors as Record<string, string>;
  const itemsTotal = useMemo(
    () =>
      form.data.items.reduce(
        (total, item) => total + argentineMoneyValue(item.line_total),
        0,
      ),
    [form.data.items],
  );
  const totalAmount = argentineMoneyValue(form.data.total_amount);
  const difference = totalAmount - itemsTotal;

  const updateItem = <K extends keyof VoucherItemForm>(
    indexToUpdate: number,
    field: K,
    value: VoucherItemForm[K],
  ) => {
    form.setData(
      'items',
      form.data.items.map((item, index) =>
        index === indexToUpdate ? { ...item, [field]: value } : item,
      ),
    );
  };

  const selectArticle = (indexToUpdate: number, article: Article | null) => {
    form.setData(
      'items',
      form.data.items.map((item, index) =>
        index === indexToUpdate
          ? {
              ...item,
              article_id: article ? String(article.id) : null,
              article_label: article
                ? `${article.internal_code} · ${article.description}`
                : null,
              description: article?.description ?? item.description,
              unit_of_measure: article?.unit_of_measure ?? item.unit_of_measure,
            }
          : item,
      ),
    );
  };

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    form.transform((data) => ({
      ...data,
      total_amount: canonicalMoney(data.total_amount),
      items: data.items.map((item) => ({
        ...item,
        quantity: canonicalMoney(item.quantity),
        unit_price: canonicalMoney(item.unit_price),
        line_total: canonicalMoney(item.line_total),
      })),
    }));
    form.submit(store(), {
      onSuccess: () => toast.success('Comprobante registrado correctamente.'),
      onError: () =>
        toast.error('Revisá los datos del comprobante y sus ítems.'),
    });
  };

  return (
    <>
      <Head title="Registrar comprobante de proveedor" />

      <form
        onSubmit={submit}
        className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8"
      >
        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <Heading
            title="Registrar comprobante"
            description="Transcribí la cabecera y todos los ítems del documento recibido."
          />
          <Button variant="outline" asChild>
            <Link href={index()}>
              <ArrowLeft className="size-4" />
              Volver al listado
            </Link>
          </Button>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Cabecera del comprobante</CardTitle>
          </CardHeader>
          <CardContent className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="supplier_id">Proveedor *</Label>
              <Select
                value={form.data.supplier_id}
                onValueChange={(value) => form.setData('supplier_id', value)}
              >
                <SelectTrigger id="supplier_id" className="w-full">
                  <SelectValue placeholder="Seleccionar proveedor" />
                </SelectTrigger>
                <SelectContent>
                  {suppliers.map((supplier) => (
                    <SelectItem key={supplier.id} value={String(supplier.id)}>
                      {supplier.business_name} · {supplier.tax_id}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <InputError message={errors.supplier_id} />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="type">Tipo *</Label>
              <Select
                value={form.data.type}
                onValueChange={(value) => form.setData('type', value)}
              >
                <SelectTrigger id="type" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {voucherTypes.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <InputError message={errors.type} />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="letter">Letra *</Label>
              <Select
                value={form.data.letter}
                onValueChange={(value) => form.setData('letter', value)}
              >
                <SelectTrigger id="letter" className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {letters.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <InputError message={errors.letter} />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="point_of_sale">Punto de venta *</Label>
              <Input
                id="point_of_sale"
                inputMode="numeric"
                placeholder="0001"
                value={form.data.point_of_sale}
                onChange={(event) =>
                  form.setData(
                    'point_of_sale',
                    onlyDigits(event.target.value, 4),
                  )
                }
                onBlur={() =>
                  form.data.point_of_sale &&
                  form.setData(
                    'point_of_sale',
                    form.data.point_of_sale.padStart(4, '0'),
                  )
                }
              />
              <InputError message={errors.point_of_sale} />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="number">Número *</Label>
              <Input
                id="number"
                inputMode="numeric"
                placeholder="00000001"
                value={form.data.number}
                onChange={(event) =>
                  form.setData('number', onlyDigits(event.target.value, 8))
                }
                onBlur={() =>
                  form.data.number &&
                  form.setData('number', form.data.number.padStart(8, '0'))
                }
              />
              <InputError message={errors.number} />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="issue_date">Fecha de emisión *</Label>
              <Input
                id="issue_date"
                type="date"
                max={today}
                value={form.data.issue_date}
                onChange={(event) =>
                  form.setData('issue_date', event.target.value)
                }
              />
              <InputError message={errors.issue_date} />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="due_date">Fecha de vencimiento</Label>
              <Input
                id="due_date"
                type="date"
                min={form.data.issue_date}
                value={form.data.due_date}
                onChange={(event) =>
                  form.setData('due_date', event.target.value)
                }
              />
              <InputError message={errors.due_date} />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="total_amount">Importe total transcripto *</Label>
              <Input
                id="total_amount"
                inputMode="decimal"
                placeholder="0,00"
                value={form.data.total_amount}
                onChange={(event) =>
                  form.setData(
                    'total_amount',
                    formatArgentineMoneyInput(event.target.value),
                  )
                }
                onBlur={() =>
                  form.setData(
                    'total_amount',
                    completeArgentineMoney(form.data.total_amount),
                  )
                }
              />
              <InputError message={errors.total_amount} />
            </div>

            <div className="space-y-1.5 md:col-span-2 xl:col-span-3">
              <Label htmlFor="notes">Observaciones</Label>
              <Textarea
                id="notes"
                rows={3}
                maxLength={2000}
                value={form.data.notes}
                onChange={(event) => form.setData('notes', event.target.value)}
              />
              <InputError message={errors.notes} />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex-row items-center justify-between gap-4">
            <div>
              <CardTitle>Ítems del documento</CardTitle>
              <p className="mt-1 text-sm text-muted-foreground">
                Usá “Concepto sin artículo” para cargos, descuentos o ajustes.
              </p>
            </div>
            <Button
              type="button"
              variant="outline"
              onClick={() =>
                form.setData('items', [...form.data.items, emptyItem()])
              }
            >
              <Plus className="size-4" /> Agregar ítem
            </Button>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            <InputError message={errors.items} />
            {form.data.items.map((item, itemIndex) => (
              <div
                key={itemIndex}
                className="grid gap-3 rounded-lg border bg-muted/20 p-4 md:grid-cols-2 xl:grid-cols-12"
              >
                <div className="space-y-1.5 md:col-span-2 xl:col-span-3">
                  <ArticleSearch
                    selectedLabel={item.article_label}
                    onSelect={(article) => selectArticle(itemIndex, article)}
                  />
                  <InputError
                    message={errors[`items.${itemIndex}.article_id`]}
                  />
                </div>

                <div className="space-y-1.5 md:col-span-2 xl:col-span-3">
                  <Label>Descripción original *</Label>
                  <Input
                    value={item.description}
                    maxLength={500}
                    onChange={(event) =>
                      updateItem(itemIndex, 'description', event.target.value)
                    }
                  />
                  <InputError
                    message={errors[`items.${itemIndex}.description`]}
                  />
                </div>

                <div className="space-y-1.5 xl:col-span-1">
                  <Label>Cantidad *</Label>
                  <Input
                    inputMode="decimal"
                    value={item.quantity}
                    onChange={(event) =>
                      updateItem(
                        itemIndex,
                        'quantity',
                        formatDecimalInput(event.target.value, 2),
                      )
                    }
                    onBlur={() =>
                      updateItem(
                        itemIndex,
                        'quantity',
                        completeDecimalInput(item.quantity, 2),
                      )
                    }
                  />
                  <InputError message={errors[`items.${itemIndex}.quantity`]} />
                </div>

                <div className="space-y-1.5 xl:col-span-1">
                  <Label>Unidad *</Label>
                  <Input
                    value={item.unit_of_measure}
                    maxLength={50}
                    onChange={(event) =>
                      updateItem(
                        itemIndex,
                        'unit_of_measure',
                        event.target.value,
                      )
                    }
                  />
                  <InputError
                    message={errors[`items.${itemIndex}.unit_of_measure`]}
                  />
                </div>

                <div className="space-y-1.5 xl:col-span-2">
                  <Label>Precio unitario *</Label>
                  <Input
                    inputMode="decimal"
                    placeholder="0,00"
                    value={item.unit_price}
                    onChange={(event) =>
                      updateItem(
                        itemIndex,
                        'unit_price',
                        formatArgentineMoneyInput(event.target.value),
                      )
                    }
                    onBlur={() =>
                      updateItem(
                        itemIndex,
                        'unit_price',
                        completeArgentineMoney(item.unit_price),
                      )
                    }
                  />
                  <InputError
                    message={errors[`items.${itemIndex}.unit_price`]}
                  />
                </div>

                <div className="space-y-1.5 xl:col-span-2">
                  <Label>Importe del ítem *</Label>
                  <div className="flex gap-2">
                    <Input
                      inputMode="decimal"
                      placeholder="0,00"
                      value={item.line_total}
                      onChange={(event) =>
                        updateItem(
                          itemIndex,
                          'line_total',
                          formatArgentineMoneyInput(event.target.value),
                        )
                      }
                      onBlur={() =>
                        updateItem(
                          itemIndex,
                          'line_total',
                          completeArgentineMoney(item.line_total),
                        )
                      }
                    />
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      disabled={form.data.items.length === 1}
                      onClick={() =>
                        form.setData(
                          'items',
                          form.data.items.filter(
                            (_, index) => index !== itemIndex,
                          ),
                        )
                      }
                      aria-label={`Quitar ítem ${itemIndex + 1}`}
                    >
                      <Trash2 className="size-4" />
                    </Button>
                  </div>
                  <InputError
                    message={errors[`items.${itemIndex}.line_total`]}
                  />
                </div>
              </div>
            ))}
          </CardContent>
        </Card>

        <Card>
          <CardContent className="grid gap-4 pt-6 md:grid-cols-3">
            <div>
              <p className="text-sm text-muted-foreground">Suma de ítems</p>
              <p className="text-xl font-semibold">
                {formatCurrency(itemsTotal)}
              </p>
            </div>
            <div>
              <p className="text-sm text-muted-foreground">
                Total del documento
              </p>
              <p className="text-xl font-semibold">
                {formatCurrency(totalAmount)}
              </p>
            </div>
            <div>
              <p className="text-sm text-muted-foreground">
                Diferencia informativa
              </p>
              <p className="text-xl font-semibold">
                {formatCurrency(difference)}
              </p>
            </div>
            <div className="flex justify-end md:col-span-3">
              <Button
                type="submit"
                disabled={form.processing || suppliers.length === 0}
              >
                {form.processing && <Loader2 className="size-4 animate-spin" />}
                Registrar comprobante
              </Button>
            </div>
          </CardContent>
        </Card>
      </form>
    </>
  );
}

function ArticleSearch({
  selectedLabel,
  onSelect,
}: {
  selectedLabel: string | null;
  onSelect: (article: Article | null) => void;
}) {
  const [search, setSearch] = useState('');
  const [results, setResults] = useState<Article[]>([]);
  const [isSearching, setIsSearching] = useState(false);
  const [hasSearched, setHasSearched] = useState(false);

  useEffect(() => {
    const term = search.trim();

    if (term.length < 2) {
      return;
    }

    const abortController = new AbortController();
    const timer = window.setTimeout(async () => {
      setIsSearching(true);

      try {
        const response = await fetch(
          searchArticles.url({ query: { search: term } }),
          {
            headers: { Accept: 'application/json' },
            signal: abortController.signal,
          },
        );

        if (!response.ok) {
          setResults([]);

          return;
        }

        setResults((await response.json()) as Article[]);
      } catch (error) {
        if (!(error instanceof DOMException && error.name === 'AbortError')) {
          setResults([]);
        }
      } finally {
        if (!abortController.signal.aborted) {
          setIsSearching(false);
          setHasSearched(true);
        }
      }
    }, 300);

    return () => {
      window.clearTimeout(timer);
      abortController.abort();
    };
  }, [search]);

  const updateSearch = (value: string) => {
    setSearch(value);

    if (value.trim().length < 2) {
      setResults([]);
      setHasSearched(false);
      setIsSearching(false);
    }
  };

  const chooseArticle = (article: Article) => {
    onSelect(article);
    setSearch('');
    setResults([]);
    setHasSearched(false);
  };

  return (
    <div className="space-y-2">
      <div className="relative">
        <Search className="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground" />
        <Input
          value={search}
          onChange={(event) => updateSearch(event.target.value)}
          placeholder="Buscar por código, descripción o barras"
          className="pr-10 pl-9"
          autoComplete="off"
        />
        {isSearching && (
          <Loader2 className="absolute top-2.5 right-3 size-4 animate-spin text-muted-foreground" />
        )}

        {(results.length > 0 || (hasSearched && !isSearching)) && (
          <div className="absolute z-50 mt-1 max-h-64 w-full overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-lg">
            {results.length === 0 ? (
              <p className="px-3 py-4 text-center text-sm text-muted-foreground">
                No se encontraron artículos activos.
              </p>
            ) : (
              results.map((article) => (
                <Button
                  key={article.id}
                  type="button"
                  variant="ghost"
                  className="h-auto w-full justify-start px-3 py-2 text-left whitespace-normal"
                  onClick={() => chooseArticle(article)}
                >
                  <span className="font-mono text-xs font-semibold">
                    {article.internal_code}
                  </span>
                  <span className="text-sm">{article.description}</span>
                </Button>
              ))
            )}
          </div>
        )}
      </div>

      <div className="flex flex-wrap items-center gap-2">
        <p className="min-w-0 flex-1 truncate text-xs text-muted-foreground">
          {selectedLabel ?? 'Concepto sin artículo'}
        </p>
        {selectedLabel && (
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => onSelect(null)}
          >
            Usar concepto
          </Button>
        )}
      </div>
    </div>
  );
}

CreateSupplierVoucher.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Comprobantes de proveedores', href: index() },
    { title: 'Registrar comprobante', href: '#' },
  ] satisfies BreadcrumbItem[],
};
