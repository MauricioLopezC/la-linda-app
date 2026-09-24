import { Head, Link, useForm } from '@inertiajs/react';
import {
  AlertCircle,
  ArrowLeft,
  ChevronDown,
  ChevronUp,
  ChevronsUpDown,
  FileText,
  Loader2,
  PackageCheck,
  Plus,
  Trash2,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { store } from '@/actions/App/Http/Controllers/Purchasing/SupplierVoucherController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
import { cn, formatCurrency } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
  associableInvoices as searchAssociableInvoices,
  associablePurchaseOrders as searchAssociablePurchaseOrders,
  articles as searchArticles,
  index,
} from '@/routes/purchasing/vouchers';
import type { BreadcrumbItem } from '@/types';

type Supplier = App.Data.Purchasing.SupplierOptionData;
type Option = App.Data.Purchasing.SupplierVoucherOptionData;
type Article = App.Data.Purchasing.PurchaseOrderArticleOptionData;
type AssociableInvoice = App.Data.Purchasing.AssociableInvoiceOptionData;
type ImputablePurchaseOrder = App.Data.Purchasing.PurchaseOrderImputableData;
type Warehouse = App.Data.Purchasing.PurchaseOrderWarehouseOptionData;

const INVOICE_TYPE = 'factura';
const CREDIT_NOTE_TYPE = 'nota_credito';
const REMITO_TYPE = 'remito';

/** Unit stored for concept lines (no catalog article): they only carry description + amount. */
const CONCEPT_UNIT = '—';

type VoucherItemForm = {
  article_id: string | null;
  article_label: string | null;
  description: string;
  quantity: string;
  unit_of_measure: string;
  unit_price: string;
  line_total: string;
  line_total_touched: boolean;
  purchase_order_item_id?: number | null;
  purchase_order_number?: string | null;
  pending_quantity?: number | null;
};

type VoucherFormData = {
  supplier_id: string;
  type: string;
  letter: string;
  point_of_sale: string;
  number: string;
  issue_date: string;
  due_date: string;
  warehouse_id: string;
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
  warehouses: Warehouse[];
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
  purchase_order_item_id: null,
  purchase_order_number: null,
  pending_quantity: null,
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
  warehouses,
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
    warehouse_id: '',
    total_amount: '',
    notes: '',
    associated_invoice_id: null,
    associated_amount: '',
    items: [emptyItem()],
  });

  const errors = form.errors as Record<string, string>;
  const isCreditNote = form.data.type === CREDIT_NOTE_TYPE;
  const isRemito = form.data.type === REMITO_TYPE;
  // Only invoices (bill) and remitos (receive) cover purchase order lines.
  const canImputeToPurchaseOrder = form.data.type === INVOICE_TYPE || isRemito;

  const hasImputedItems = useMemo(
    () =>
      form.data.items.some(
        (item) =>
          item.purchase_order_item_id !== null &&
          item.purchase_order_item_id !== undefined,
      ),
    [form.data.items],
  );

  const availableLetters = useMemo(() => {
    if (isRemito) {
      return letters.filter((l) => ['R', 'X'].includes(l.value));
    }

    return letters.filter((l) => !['R', 'X'].includes(l.value));
  }, [isRemito, letters]);

  const changeType = (value: string) => {
    const nextIsRemito = value === REMITO_TYPE;
    form.setData((data) => ({
      ...data,
      type: value,
      letter: nextIsRemito
        ? ['R', 'X'].includes(data.letter)
          ? data.letter
          : 'R'
        : ['R', 'X'].includes(data.letter)
          ? 'A'
          : data.letter,
      due_date: nextIsRemito ? '' : data.due_date,
      warehouse_id: nextIsRemito ? data.warehouse_id : '',
      ...(value === CREDIT_NOTE_TYPE
        ? {}
        : { associated_invoice_id: null, associated_amount: '' }),
      // Each type covers a different pending quantity of the order (or none),
      // so lines linked under the previous type are unlinked.
      items:
        value === data.type
          ? data.items
          : data.items.map((item) => ({
              ...item,
              purchase_order_item_id: null,
              purchase_order_number: null,
              pending_quantity: null,
            })),
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
              // Coming from a concept line, re-enable the quantity × price autocalc.
              line_total_touched:
                article !== null && item.article_id === null
                  ? false
                  : item.line_total_touched,
              purchase_order_item_id: null,
              purchase_order_number: null,
              pending_quantity: null,
            }
          : item,
      ),
    );
  };

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    form.transform((data) => ({
      ...data,
      due_date: isRemito ? '' : data.due_date,
      warehouse_id: isRemito ? data.warehouse_id : '',
      total_amount: data.total_amount
        ? canonicalMoney(data.total_amount)
        : isRemito
          ? '0.00'
          : '',
      associated_invoice_id:
        data.type === CREDIT_NOTE_TYPE ? data.associated_invoice_id : null,
      associated_amount:
        data.type === CREDIT_NOTE_TYPE && data.associated_invoice_id
          ? canonicalMoney(data.associated_amount)
          : '',
      items: data.items.map((item) => {
        const isConceptRow = item.article_id === null;

        return {
          article_id: item.article_id,
          article_label: item.article_label,
          description: item.description,
          unit_of_measure: isConceptRow ? CONCEPT_UNIT : item.unit_of_measure,
          quantity: isConceptRow ? '1' : canonicalMoney(item.quantity),
          unit_price: isConceptRow
            ? item.line_total
              ? canonicalMoney(item.line_total)
              : isRemito
                ? '0.00'
                : ''
            : item.unit_price
              ? canonicalMoney(item.unit_price)
              : isRemito
                ? '0.00'
                : '',
          line_total: item.line_total
            ? canonicalMoney(item.line_total)
            : isRemito
              ? '0.00'
              : '',
          purchase_order_item_id: item.purchase_order_item_id ?? null,
        };
      }),
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
                  {availableLetters.map((option) => (
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

            {isRemito ? (
              <div className="space-y-1.5">
                <Label htmlFor="warehouse_id">Depósito de destino *</Label>
                {hasImputedItems ? (
                  <div className="space-y-1">
                    <div className="flex items-center gap-2">
                      <Input
                        id="warehouse_id"
                        disabled
                        value={
                          warehouses.find(
                            (w) => String(w.id) === form.data.warehouse_id,
                          )?.name ?? 'Depósito de la OC'
                        }
                        className="bg-muted"
                      />
                      <Badge
                        variant="outline"
                        className="shrink-0 border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300"
                      >
                        Derivado de la OC
                      </Badge>
                    </div>
                    <p className="text-xs text-muted-foreground">
                      Se toma automáticamente de las órdenes de compra
                      asociadas.
                    </p>
                  </div>
                ) : (
                  <Select
                    value={form.data.warehouse_id}
                    onValueChange={(value) =>
                      form.setData('warehouse_id', value)
                    }
                  >
                    <SelectTrigger id="warehouse_id" className="w-full">
                      <SelectValue placeholder="Seleccionar depósito" />
                    </SelectTrigger>
                    <SelectContent>
                      {warehouses.map((warehouse) => (
                        <SelectItem
                          key={warehouse.id}
                          value={String(warehouse.id)}
                        >
                          {warehouse.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
                <InputError message={errors.warehouse_id} />
              </div>
            ) : (
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
            )}

            <div className="space-y-1.5">
              <Label htmlFor="total_amount">
                Importe total transcripto {isRemito ? '(opcional)' : '*'}
              </Label>
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

        {form.data.supplier_id && canImputeToPurchaseOrder && (
          <PurchaseOrderAssociation
            supplierId={form.data.supplier_id}
            voucherType={form.data.type}
            currentItems={form.data.items}
            isRemito={isRemito}
            selectedWarehouseId={form.data.warehouse_id}
            onSetWarehouse={(warehouseId) =>
              form.setData('warehouse_id', warehouseId)
            }
            onImportItems={(importedItems) => {
              const isOnlyOneEmpty =
                form.data.items.length === 1 &&
                form.data.items[0].article_id === null &&
                form.data.items[0].description.trim() === '' &&
                form.data.items[0].line_total.trim() === '';

              form.setData((data) => ({
                ...data,
                items: isOnlyOneEmpty
                  ? importedItems
                  : [...data.items, ...importedItems],
              }));
            }}
          />
        )}

        <Card>
          <CardHeader className="flex-row items-center justify-between gap-4">
            <div>
              <CardTitle>Ítems del documento</CardTitle>
              <p className="mt-1 text-sm text-muted-foreground">
                {isRemito
                  ? 'El remito debe incluir al menos un artículo del catálogo para ingresar stock. Los importes son opcionales.'
                  : 'Usá “Concepto sin artículo” para cargos, descuentos o ajustes: ese tipo de renglón solo pide descripción e importe.'}
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
            {form.data.items.map((item, itemIndex) => {
              const isConcept = item.article_id === null;

              return (
                <div
                  key={itemIndex}
                  className="grid gap-3 rounded-lg border bg-muted/20 p-4 md:grid-cols-2 xl:grid-cols-12"
                >
                  {item.purchase_order_item_id && (
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b pb-2 text-xs md:col-span-2 xl:col-span-12">
                      <div className="flex flex-wrap items-center gap-2">
                        <Badge
                          variant="outline"
                          className="border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-300"
                        >
                          <FileText className="mr-1 size-3" />
                          Imputando a {item.purchase_order_number}
                        </Badge>
                        <span className="text-muted-foreground">
                          {isRemito
                            ? 'Pendiente de recibir en OC:'
                            : 'Pendiente de facturar en OC:'}{' '}
                          <strong className="font-semibold text-foreground">
                            {item.pending_quantity}
                          </strong>{' '}
                          {item.unit_of_measure}
                        </span>
                      </div>
                      {(() => {
                        const enteredQty =
                          Number(canonicalMoney(item.quantity)) || 0;
                        const pendingQty = Number(item.pending_quantity) || 0;

                        if (enteredQty > pendingQty) {
                          const excess = (enteredQty - pendingQty)
                            .toFixed(3)
                            .replace(/\.?0+$/, '');

                          return (
                            <Badge
                              variant="outline"
                              className="border-amber-400 bg-amber-50 text-amber-900 dark:border-amber-700 dark:bg-amber-950/60 dark:text-amber-200"
                            >
                              <AlertCircle className="mr-1 size-3 text-amber-600 dark:text-amber-400" />
                              Excedente: +{excess} {item.unit_of_measure}{' '}
                              {isRemito
                                ? '(se ingresará al inventario como excedente aceptado)'
                                : '(se facturará como excedente aceptado)'}
                            </Badge>
                          );
                        }

                        return null;
                      })()}
                    </div>
                  )}

                  <div className="space-y-1.5 md:col-span-2 xl:col-span-3">
                    <ArticleSearch
                      selectedLabel={item.article_label}
                      onSelect={(article) => selectArticle(itemIndex, article)}
                    />
                    <InputError
                      message={errors[`items.${itemIndex}.article_id`]}
                    />
                  </div>

                  <div
                    className={cn(
                      'space-y-1.5 md:col-span-2',
                      isConcept ? 'xl:col-span-6' : 'xl:col-span-3',
                    )}
                  >
                    <Label>
                      {isConcept ? 'Descripción *' : 'Descripción original *'}
                    </Label>
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

                  {!isConcept && (
                    <div className="space-y-1.5 xl:col-span-1">
                      <Label>Cantidad *</Label>
                      <Input
                        inputMode="decimal"
                        value={item.quantity}
                        onChange={(event) =>
                          updateItem(
                            itemIndex,
                            'quantity',
                            formatDecimalInput(event.target.value, 3),
                          )
                        }
                        onBlur={() =>
                          updateItem(
                            itemIndex,
                            'quantity',
                            completeDecimalInput(item.quantity, 3),
                          )
                        }
                      />
                      <InputError
                        message={errors[`items.${itemIndex}.quantity`]}
                      />
                    </div>
                  )}

                  {!isConcept && (
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
                  )}

                  {!isConcept && (
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
                  )}

                  <div
                    className={cn(
                      'space-y-1.5',
                      isConcept ? 'xl:col-span-3' : 'xl:col-span-2',
                    )}
                  >
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
              );
            })}
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

function PurchaseOrderAssociation({
  supplierId,
  voucherType,
  currentItems,
  isRemito,
  selectedWarehouseId,
  onSetWarehouse,
  onImportItems,
}: {
  supplierId: string;
  voucherType: string;
  currentItems: VoucherItemForm[];
  isRemito: boolean;
  selectedWarehouseId: string;
  onSetWarehouse: (warehouseId: string) => void;
  onImportItems: (items: VoucherItemForm[]) => void;
}) {
  const [orders, setOrders] = useState<ImputablePurchaseOrder[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [hasLoaded, setHasLoaded] = useState(false);
  const [expandedOrders, setExpandedOrders] = useState<Record<number, boolean>>(
    {},
  );

  const hasImputedInItems = useMemo(
    () =>
      currentItems.some(
        (ci) =>
          ci.purchase_order_item_id !== null &&
          ci.purchase_order_item_id !== undefined,
      ),
    [currentItems],
  );

  useEffect(() => {
    if (supplierId === '') {
      return;
    }

    const abortController = new AbortController();
    const timer = window.setTimeout(() => {
      setIsLoading(true);

      fetch(
        searchAssociablePurchaseOrders.url({
          query: { supplier_id: supplierId, type: voucherType },
        }),
        {
          headers: { Accept: 'application/json' },
          signal: abortController.signal,
        },
      )
        .then((res) => (res.ok ? res.json() : []))
        .then((data) => setOrders(data as ImputablePurchaseOrder[]))
        .catch((error) => {
          if (!(error instanceof DOMException && error.name === 'AbortError')) {
            setOrders([]);
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
  }, [supplierId, voucherType]);

  if (supplierId === '' || (!isLoading && hasLoaded && orders.length === 0)) {
    return null;
  }

  const toggleExpand = (orderId: number) => {
    setExpandedOrders((prev) => ({ ...prev, [orderId]: !prev[orderId] }));
  };

  const importOrder = (order: ImputablePurchaseOrder) => {
    if (isRemito) {
      if (
        hasImputedInItems &&
        selectedWarehouseId !== '' &&
        String(order.warehouse_id) !== selectedWarehouseId
      ) {
        toast.error(
          `No se puede asociar esta orden: pertenece al depósito ${order.warehouse_name}, distinto al del remito.`,
        );

        return;
      }

      if (
        !hasImputedInItems &&
        selectedWarehouseId !== String(order.warehouse_id)
      ) {
        onSetWarehouse(String(order.warehouse_id));
      }
    }

    const newItems: VoucherItemForm[] = [];
    let alreadyImportedCount = 0;

    for (const item of order.items) {
      const isAlreadyIn = currentItems.some(
        (ci) => ci.purchase_order_item_id === item.id,
      );

      if (isAlreadyIn) {
        alreadyImportedCount++;
        continue;
      }

      newItems.push({
        article_id: String(item.article_id),
        article_label: `${item.article_internal_code} · ${item.article_description}`,
        description: item.article_description,
        quantity: formatDecimalInput(
          item.quantity_pending.replace('.', ','),
          3,
        ),
        unit_of_measure: item.unit_of_measure,
        unit_price: formatArgentineMoneyInput(
          Number(item.unit_price).toFixed(2).replace('.', ','),
        ),
        line_total: formatArgentineMoneyInput(
          (Number(item.quantity_pending) * Number(item.unit_price))
            .toFixed(2)
            .replace('.', ','),
        ),
        line_total_touched: false,
        purchase_order_item_id: item.id,
        purchase_order_number: item.purchase_order_number,
        pending_quantity: Number(item.quantity_pending),
      });
    }

    if (newItems.length === 0) {
      if (alreadyImportedCount > 0) {
        toast.info(
          'Los artículos de esta orden ya están en el detalle del comprobante.',
        );
      } else {
        toast.info('Esta orden no tiene renglones con saldo pendiente.');
      }

      return;
    }

    onImportItems(newItems);
    toast.success(
      `Se importaron ${newItems.length} artículos de la orden ${order.order_number}.`,
    );
  };

  const importAllOrders = () => {
    let targetOrders = orders;

    if (isRemito) {
      const targetWarehouseId =
        hasImputedInItems && selectedWarehouseId !== ''
          ? selectedWarehouseId
          : orders.length > 0
            ? String(orders[0].warehouse_id)
            : '';

      if (targetWarehouseId !== '') {
        const matchingOrders = orders.filter(
          (o) => String(o.warehouse_id) === targetWarehouseId,
        );
        const skippedCount = orders.length - matchingOrders.length;

        if (skippedCount > 0) {
          toast.warning(
            `Se omitieron ${skippedCount} órdenes por pertenecer a un depósito distinto.`,
          );
        }

        targetOrders = matchingOrders;

        if (!hasImputedInItems) {
          onSetWarehouse(targetWarehouseId);
        }
      }
    }

    const allNewItems: VoucherItemForm[] = [];
    let totalImported = 0;

    for (const order of targetOrders) {
      for (const item of order.items) {
        const isAlreadyIn =
          currentItems.some((ci) => ci.purchase_order_item_id === item.id) ||
          allNewItems.some((ni) => ni.purchase_order_item_id === item.id);

        if (!isAlreadyIn) {
          allNewItems.push({
            article_id: String(item.article_id),
            article_label: `${item.article_internal_code} · ${item.article_description}`,
            description: item.article_description,
            quantity: formatDecimalInput(
              item.quantity_pending.replace('.', ','),
              3,
            ),
            unit_of_measure: item.unit_of_measure,
            unit_price: formatArgentineMoneyInput(
              Number(item.unit_price).toFixed(2).replace('.', ','),
            ),
            line_total: formatArgentineMoneyInput(
              (Number(item.quantity_pending) * Number(item.unit_price))
                .toFixed(2)
                .replace('.', ','),
            ),
            line_total_touched: false,
            purchase_order_item_id: item.id,
            purchase_order_number: item.purchase_order_number,
            pending_quantity: Number(item.quantity_pending),
          });
          totalImported++;
        }
      }
    }

    if (allNewItems.length === 0) {
      toast.info(
        'Los artículos de las órdenes pendientes ya fueron importados.',
      );

      return;
    }

    onImportItems(allNewItems);
    toast.success(
      `Se importaron ${totalImported} artículos de las órdenes de compra.`,
    );
  };

  return (
    <Card className="border-blue-200 bg-blue-50/30 dark:border-blue-900/60 dark:bg-blue-950/20">
      <CardHeader className="flex-row items-center justify-between gap-4 pb-3">
        <div>
          <div className="flex items-center gap-2">
            <PackageCheck className="size-5 text-blue-600 dark:text-blue-400" />
            <CardTitle className="text-base">
              {isRemito
                ? 'Órdenes de compra pendientes de recibir (opcional)'
                : 'Órdenes de compra pendientes de facturar (opcional)'}
            </CardTitle>
          </div>
          <p className="mt-1 text-sm text-muted-foreground">
            {isRemito
              ? 'Podés imputar la recepción de mercadería a una o varias órdenes emitidas para este proveedor.'
              : 'Podés imputar la factura a una o varias órdenes emitidas para este proveedor.'}
          </p>
        </div>
        {!isLoading && orders.length > 0 && (
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={importAllOrders}
            className="shrink-0 bg-background"
          >
            Importar todas ({orders.length})
          </Button>
        )}
      </CardHeader>
      <CardContent className="flex flex-col gap-3">
        {isLoading && (
          <div className="flex items-center gap-2 py-4 text-sm text-muted-foreground">
            <Loader2 className="size-4 animate-spin" />
            Consultando órdenes de compra pendientes…
          </div>
        )}

        {!isLoading &&
          orders.map((order) => {
            const isExpanded = !!expandedOrders[order.id];
            const allItemsImported =
              order.items.length > 0 &&
              order.items.every((item) =>
                currentItems.some(
                  (ci) => ci.purchase_order_item_id === item.id,
                ),
              );
            const isWarehouseConflict =
              isRemito &&
              hasImputedInItems &&
              selectedWarehouseId !== '' &&
              String(order.warehouse_id) !== selectedWarehouseId;

            return (
              <div
                key={order.id}
                className="rounded-lg border bg-card p-3 shadow-xs transition-colors"
              >
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                  <div className="flex flex-wrap items-center gap-2 text-sm">
                    <span className="font-mono font-bold text-foreground">
                      {order.order_number}
                    </span>
                    <span className="text-muted-foreground">·</span>
                    <span className="text-xs text-muted-foreground">
                      Emisión: {order.issue_date_formatted}
                    </span>
                    <span className="text-muted-foreground">·</span>
                    <span className="text-xs text-muted-foreground">
                      Depósito: {order.warehouse_name}
                    </span>
                    {isWarehouseConflict && (
                      <Badge variant="destructive" className="text-xs">
                        Depósito incompatible
                      </Badge>
                    )}
                    <span className="text-muted-foreground">·</span>
                    <span className="font-mono text-xs font-semibold text-foreground">
                      {formatCurrency(order.total_amount)}
                    </span>
                    <Badge variant="secondary" className="text-xs font-normal">
                      {order.items.length}{' '}
                      {order.items.length === 1
                        ? 'ítem pendiente'
                        : 'ítems pendientes'}
                    </Badge>
                  </div>

                  <div className="flex items-center gap-2 self-end sm:self-auto">
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => toggleExpand(order.id)}
                      className="text-xs text-muted-foreground"
                    >
                      {isExpanded ? (
                        <>
                          <ChevronUp className="mr-1 size-3" />
                          Ocultar detalle
                        </>
                      ) : (
                        <>
                          <ChevronDown className="mr-1 size-3" />
                          Ver artículos
                        </>
                      )}
                    </Button>
                    <Button
                      type="button"
                      variant={allItemsImported ? 'secondary' : 'default'}
                      size="sm"
                      disabled={allItemsImported || isWarehouseConflict}
                      onClick={() => importOrder(order)}
                      className="text-xs"
                    >
                      {allItemsImported
                        ? 'Ya importada'
                        : isWarehouseConflict
                          ? 'Depósito distinto'
                          : 'Importar a ítems'}
                    </Button>
                  </div>
                </div>

                {isExpanded && (
                  <div className="mt-3 overflow-x-auto border-t pt-3">
                    <table className="w-full text-left text-xs">
                      <thead>
                        <tr className="border-b text-muted-foreground">
                          <th className="pb-1.5 font-medium">Código</th>
                          <th className="pb-1.5 font-medium">Artículo</th>
                          <th className="pb-1.5 text-center font-medium">
                            U.M.
                          </th>
                          <th className="pb-1.5 text-right font-medium">
                            Pedido
                          </th>
                          <th className="pb-1.5 text-right font-medium">
                            {isRemito ? 'Recibido' : 'Facturado'}
                          </th>
                          <th className="pb-1.5 text-right font-medium">
                            Pendiente
                          </th>
                          <th className="pb-1.5 text-right font-medium">
                            Costo pactado
                          </th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-border/50">
                        {order.items.map((item) => (
                          <tr key={item.id} className="py-1">
                            <td className="py-1.5 font-mono font-semibold">
                              {item.article_internal_code}
                            </td>
                            <td className="py-1.5">
                              {item.article_description}
                            </td>
                            <td className="py-1.5 text-center text-muted-foreground">
                              {item.unit_of_measure}
                            </td>
                            <td className="py-1.5 text-right font-mono">
                              {item.quantity_requested}
                            </td>
                            <td className="py-1.5 text-right font-mono">
                              {item.quantity_covered}
                            </td>
                            <td className="py-1.5 text-right font-mono font-semibold text-blue-600 dark:text-blue-400">
                              {item.quantity_pending}
                            </td>
                            <td className="py-1.5 text-right font-mono">
                              {formatCurrency(item.unit_price)}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            );
          })}
      </CardContent>
    </Card>
  );
}

CreateSupplierVoucher.layout = {
  breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Comprobantes de proveedores', href: index() },
    { title: 'Registrar comprobante', href: '#' },
  ] satisfies BreadcrumbItem[],
};
