import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ChevronsUpDown, Loader2, Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { store } from '@/actions/App/Http/Controllers/Purchasing/SupplierVoucherController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
  Command,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
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
  associableInvoices as searchAssociableInvoices,
  articles as searchArticles,
  index,
} from '@/routes/purchasing/vouchers';
import type { BreadcrumbItem } from '@/types';

type Supplier = App.Data.Purchasing.SupplierOptionData;
type Option = App.Data.Purchasing.SupplierVoucherOptionData;
type Article = App.Data.Purchasing.PurchaseOrderArticleOptionData;
type AssociableInvoice = App.Data.Purchasing.AssociableInvoiceOptionData;

const CREDIT_NOTE_TYPE = 'nota_credito';

type VoucherItemForm = {
  article_id: string | null;
  article_label: string | null;
  description: string;
  quantity: string;
  unit_of_measure: string;
  unit_price: string;
  line_total: string;
  line_total_touched: boolean;
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
  associated_invoice_id: string | null;
  associated_amount: string;
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
  line_total_touched: false,
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
    associated_invoice_id: null,
    associated_amount: '',
    items: [emptyItem()],
  });

  const errors = form.errors as Record<string, string>;
  const isCreditNote = form.data.type === CREDIT_NOTE_TYPE;

  const changeType = (value: string) => {
    form.setData((data) => ({
      ...data,
      type: value,
      ...(value === CREDIT_NOTE_TYPE
        ? {}
        : { associated_invoice_id: null, associated_amount: '' }),
    }));
  };

  const changeSupplier = (value: string) => {
    form.setData((data) => ({
      ...data,
      supplier_id: value,
      associated_invoice_id: null,
      associated_amount: '',
    }));
  };
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
      form.data.items.map((item, index) => {
        if (index !== indexToUpdate) {
          return item;
        }

        const nextItem = { ...item, [field]: value };

        if (
          (field === 'quantity' || field === 'unit_price') &&
          !nextItem.line_total_touched
        ) {
          const quantity = argentineMoneyValue(nextItem.quantity);
          const unitPrice = argentineMoneyValue(nextItem.unit_price);

          nextItem.line_total =
            quantity > 0 && unitPrice > 0
              ? formatArgentineMoneyInput(
                  (Math.round(quantity * unitPrice * 100) / 100)
                    .toFixed(2)
                    .replace('.', ','),
                )
              : '';
        }

        return nextItem;
      }),
    );
  };

  const setLineTotal = (indexToUpdate: number, value: string) => {
    form.setData(
      'items',
      form.data.items.map((item, index) =>
        index === indexToUpdate
          ? {
              ...item,
              line_total: value,
              line_total_touched: value.trim() !== '',
            }
          : item,
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
              description:
                article && item.description.trim() === ''
                  ? article.description
                  : item.description,
              unit_of_measure:
                article && item.unit_of_measure.trim() === ''
                  ? article.unit_of_measure
                  : item.unit_of_measure,
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
      associated_invoice_id:
        data.type === CREDIT_NOTE_TYPE ? data.associated_invoice_id : null,
      associated_amount:
        data.type === CREDIT_NOTE_TYPE && data.associated_invoice_id
          ? canonicalMoney(data.associated_amount)
          : '',
      items: data.items.map((item) => ({
        article_id: item.article_id,
        article_label: item.article_label,
        description: item.description,
        unit_of_measure: item.unit_of_measure,
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
                onValueChange={changeSupplier}
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
              <Select value={form.data.type} onValueChange={changeType}>
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

        {isCreditNote && (
          <CreditNoteAssociation
            supplierId={form.data.supplier_id}
            creditNoteTotal={form.data.total_amount}
            invoiceId={form.data.associated_invoice_id}
            amount={form.data.associated_amount}
            invoiceError={errors.associated_invoice_id}
            amountError={errors.associated_amount}
            onChange={(invoiceId, amount) =>
              form.setData((data) => ({
                ...data,
                associated_invoice_id: invoiceId,
                associated_amount: amount,
              }))
            }
          />
        )}

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
                        setLineTotal(
                          itemIndex,
                          formatArgentineMoneyInput(event.target.value),
                        )
                      }
                      onBlur={() =>
                        setLineTotal(
                          itemIndex,
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

function CreditNoteAssociation({
  supplierId,
  creditNoteTotal,
  invoiceId,
  amount,
  invoiceError,
  amountError,
  onChange,
}: {
  supplierId: string;
  creditNoteTotal: string;
  invoiceId: string | null;
  amount: string;
  invoiceError?: string;
  amountError?: string;
  onChange: (invoiceId: string | null, amount: string) => void;
}) {
  const [open, setOpen] = useState(false);
  const [invoices, setInvoices] = useState<AssociableInvoice[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [hasLoaded, setHasLoaded] = useState(false);

  useEffect(() => {
    if (supplierId === '') {
      return;
    }

    const abortController = new AbortController();
    const timer = window.setTimeout(() => {
      setIsLoading(true);

      fetch(
        searchAssociableInvoices.url({ query: { supplier_id: supplierId } }),
        {
          headers: { Accept: 'application/json' },
          signal: abortController.signal,
        },
      )
        .then((response) => (response.ok ? response.json() : []))
        .then((data) => setInvoices(data as AssociableInvoice[]))
        .catch((error) => {
          if (!(error instanceof DOMException && error.name === 'AbortError')) {
            setInvoices([]);
          }
        })
        .finally(() => {
          if (!abortController.signal.aborted) {
            setIsLoading(false);
            setHasLoaded(true);
          }
        });
    }, 150);

    return () => {
      window.clearTimeout(timer);
      abortController.abort();
    };
  }, [supplierId]);

  const selectedInvoice = invoices.find(
    (invoice) => String(invoice.id) === invoiceId,
  );

  const chooseInvoice = (invoice: AssociableInvoice | null) => {
    setOpen(false);

    if (invoice === null) {
      onChange(null, '');

      return;
    }

    const noteTotal = argentineMoneyValue(creditNoteTotal);
    const invoiceOutstanding = Number(invoice.outstanding_amount) || 0;
    const suggested =
      noteTotal > 0
        ? Math.min(noteTotal, invoiceOutstanding)
        : invoiceOutstanding;

    onChange(
      String(invoice.id),
      formatArgentineMoneyInput(suggested.toFixed(2).replace('.', ',')),
    );
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>Asociar a una factura (opcional)</CardTitle>
        <p className="mt-1 text-sm text-muted-foreground">
          Si la nota de crédito responde a una factura puntual, vinculala acá y
          se descontará de su saldo al registrarla. Si no, dejala libre para
          compensarla en una orden de pago.
        </p>
      </CardHeader>
      <CardContent className="grid gap-4 md:grid-cols-2">
        <div className="space-y-1.5">
          <Label>Factura del proveedor</Label>
          <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
              <Button
                type="button"
                variant="outline"
                role="combobox"
                aria-expanded={open}
                disabled={supplierId === ''}
                className="w-full justify-between font-normal"
              >
                <span className="truncate">
                  {selectedInvoice
                    ? `${selectedInvoice.formatted_number} · saldo ${formatCurrency(selectedInvoice.outstanding_amount)}`
                    : supplierId === ''
                      ? 'Elegí primero un proveedor'
                      : 'Sin factura asociada'}
                </span>
                <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
              </Button>
            </PopoverTrigger>
            <PopoverContent
              className="w-[var(--radix-popover-trigger-width)] p-0"
              align="start"
            >
              <Command>
                <CommandInput placeholder="Buscar factura" />
                <CommandList>
                  {isLoading && (
                    <div className="flex items-center gap-2 px-3 py-4 text-sm text-muted-foreground">
                      <Loader2 className="size-4 animate-spin" />
                      Buscando facturas…
                    </div>
                  )}
                  {!isLoading && hasLoaded && invoices.length === 0 && (
                    <p className="px-3 py-4 text-center text-sm text-muted-foreground">
                      El proveedor no tiene facturas con saldo pendiente.
                    </p>
                  )}
                  <CommandItem
                    value="__sin_factura__"
                    onSelect={() => chooseInvoice(null)}
                  >
                    Sin factura asociada
                  </CommandItem>
                  {invoices.map((invoice) => (
                    <CommandItem
                      key={invoice.id}
                      value={invoice.formatted_number}
                      onSelect={() => chooseInvoice(invoice)}
                      className="flex-col items-start gap-0.5"
                    >
                      <span className="text-sm font-semibold">
                        {invoice.formatted_number}
                      </span>
                      <span className="text-xs text-muted-foreground">
                        {invoice.issue_date_formatted} · saldo{' '}
                        {formatCurrency(invoice.outstanding_amount)}
                      </span>
                    </CommandItem>
                  ))}
                </CommandList>
              </Command>
            </PopoverContent>
          </Popover>
          <InputError message={invoiceError} />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="associated_amount">Importe a aplicar</Label>
          <Input
            id="associated_amount"
            inputMode="decimal"
            placeholder="0,00"
            disabled={!invoiceId}
            value={amount}
            onChange={(event) =>
              onChange(invoiceId, formatArgentineMoneyInput(event.target.value))
            }
            onBlur={() => onChange(invoiceId, completeArgentineMoney(amount))}
          />
          <InputError message={amountError} />
        </div>
      </CardContent>
    </Card>
  );
}

function ArticleSearch({
  selectedLabel,
  onSelect,
}: {
  selectedLabel: string | null;
  onSelect: (article: Article | null) => void;
}) {
  const [open, setOpen] = useState(false);
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

  const chooseArticle = (article: Article | null) => {
    onSelect(article);
    setOpen(false);
    setSearch('');
    setResults([]);
    setHasSearched(false);
  };

  return (
    <div className="space-y-1.5">
      <Label>Artículo del catálogo</Label>
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            type="button"
            variant="outline"
            role="combobox"
            aria-expanded={open}
            className="w-full justify-between font-normal"
          >
            <span className="truncate">
              {selectedLabel ?? 'Concepto sin artículo'}
            </span>
            <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
          </Button>
        </PopoverTrigger>
        <PopoverContent
          className="w-[var(--radix-popover-trigger-width)] p-0"
          align="start"
        >
          <Command shouldFilter={false}>
            <CommandInput
              value={search}
              onValueChange={updateSearch}
              placeholder="Buscar por código, descripción o barras"
            />
            <CommandList>
              {isSearching && (
                <div className="flex items-center gap-2 px-3 py-4 text-sm text-muted-foreground">
                  <Loader2 className="size-4 animate-spin" />
                  Buscando…
                </div>
              )}
              {!isSearching && hasSearched && results.length === 0 && (
                <p className="px-3 py-4 text-center text-sm text-muted-foreground">
                  No se encontraron artículos activos.
                </p>
              )}
              <CommandItem
                value="__concepto_sin_articulo__"
                onSelect={() => chooseArticle(null)}
              >
                Usar concepto sin artículo
              </CommandItem>
              {results.map((article) => (
                <CommandItem
                  key={article.id}
                  value={String(article.id)}
                  onSelect={() => chooseArticle(article)}
                  className="flex-col items-start gap-0.5"
                >
                  <span className="font-mono text-xs font-semibold">
                    {article.internal_code}
                  </span>
                  <span className="text-sm">{article.description}</span>
                </CommandItem>
              ))}
            </CommandList>
          </Command>
        </PopoverContent>
      </Popover>
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
