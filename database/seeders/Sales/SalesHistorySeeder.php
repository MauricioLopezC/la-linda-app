<?php

namespace Database\Seeders\Sales;

use App\Actions\Ecommerce\AddArticleToCart;
use App\Actions\Ecommerce\ClearCart;
use App\Actions\Ecommerce\DeliverWebOrder;
use App\Actions\Ecommerce\GetOnlineAvailability;
use App\Actions\Ecommerce\MarkWebOrderAsPaid;
use App\Actions\Ecommerce\PlaceWebOrder;
use App\Actions\Ecommerce\ReserveWebOrderStock;
use App\Actions\Ecommerce\StartWebOrderPreparation;
use App\Actions\Purchasing\CreateSupplierVoucher;
use App\Actions\Purchasing\IssuePaymentOrder;
use App\Actions\Sales\AddArticleToSale;
use App\Actions\Sales\ChangeSaleCustomer;
use App\Actions\Sales\CloseCashSession;
use App\Actions\Sales\ConfirmSalePayment;
use App\Actions\Sales\DiscardSale;
use App\Actions\Sales\GetCashSessionExpectedTotals;
use App\Actions\Sales\OpenCashSession;
use App\Actions\Sales\OpenSale;
use App\Concerns\ConvertsMoneyToCents;
use App\Enums\Ecommerce\DeliveryMethod;
use App\Enums\Pricing\PriceListChannel;
use App\Enums\Purchasing\SupplierTaxCondition;
use App\Enums\Purchasing\SupplierVoucherLetter;
use App\Enums\Purchasing\SupplierVoucherType;
use App\Enums\Sales\CashDenomination;
use App\Enums\Sales\PaymentMethodKind;
use App\Enums\Sales\SaleStatus;
use App\Enums\Security\UserRole;
use App\Models\Catalog\Article;
use App\Models\Catalog\ArticleSupplier;
use App\Models\Customers\Customer;
use App\Models\Ecommerce\WebOrder;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\Warehouse;
use App\Models\Organization\Branch;
use App\Models\Pricing\PriceList;
use App\Models\Purchasing\Supplier;
use App\Models\Purchasing\SupplierVoucher;
use App\Models\Sales\CashSession;
use App\Models\Sales\PaymentMethod;
use App\Models\Sales\PointOfSale;
use App\Models\Sales\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Simulates the last months of operation so the management dashboard (EPIC-20) has history:
 * counter sales with their cash sessions, online orders from cart to delivery, and the supplier
 * restocking (remito + invoice, paid 30 days later) that keeps the stock from going negative.
 *
 * Everything goes through the real Actions with the clock moved to each event, so invoices,
 * VAT, cash closings and the kardex follow the same rules as production. Events of a day are
 * processed in time order across every till and the web store, so each stock movement sees the
 * balance that existed at that moment.
 *
 * The simulation is deterministic (fixed random seed) and only runs on a database without
 * confirmed sales: invoice numbering and the kardex must start with it.
 */
class SalesHistorySeeder extends Seeder
{
    use ConvertsMoneyToCents;

    private const int RANDOM_SEED = 20260416;

    /** Point of sale used on the simulated supplier vouchers, apart from SupplierVoucherSeeder's. */
    private const string SUPPLIER_VOUCHER_POINT_OF_SALE = '0010';

    /** Fraction of the general list price paid to the supplier. */
    private const float PURCHASE_COST_RATIO = 0.62;

    private const int SUPPLIER_PAYMENT_TERM_DAYS = 30;

    /**
     * Daily schedule of each counter till: base sales on a regular day and its cashier.
     *
     * @var list<array{warehouse: string, number: int, opens: string, closes: string, base_sales: int, cashier_email: string, cashier_name: string, branch: string}>
     */
    private const array TILLS = [
        ['warehouse' => 'Depósito Central', 'number' => 1, 'opens' => '08:00', 'closes' => '21:00', 'base_sales' => 7, 'cashier_email' => 'cajero', 'cashier_name' => 'Cajero', 'branch' => 'Sucursal Centro'],
        ['warehouse' => 'Depósito Central', 'number' => 2, 'opens' => '09:30', 'closes' => '20:30', 'base_sales' => 5, 'cashier_email' => 'cajero.centro2', 'cashier_name' => 'Lucía Fernández', 'branch' => 'Sucursal Centro'],
        ['warehouse' => 'Depósito Norte', 'number' => 1, 'opens' => '08:30', 'closes' => '20:30', 'base_sales' => 6, 'cashier_email' => 'cajero.norte', 'cashier_name' => 'Diego Romero', 'branch' => 'Sucursal Norte'],
    ];

    /** Sales volume by ISO day of week (1 = Monday): weekends are busier. */
    private const array WEEKDAY_FACTOR = [1 => 0.85, 2 => 0.9, 3 => 0.95, 4 => 1.0, 5 => 1.2, 6 => 1.45, 7 => 0.75];

    /**
     * Stock each warehouse is brought back to on every restock, unless recent sales ask for more.
     *
     * @var array<string, array{units: int, weighed: int}>
     */
    private const array RESTOCK_BASE_TARGET = [
        'Depósito Central' => ['units' => 90, 'weighed' => 40],
        'Depósito Norte' => ['units' => 60, 'weighed' => 25],
        'Depósito E-commerce' => ['units' => 30, 'weighed' => 12],
    ];

    /** Restocking days (ISO day of week): Monday and Thursday mornings. */
    private const array RESTOCK_WEEKDAYS = [1, 4];

    /** Online customers by CUIT/DNI with their relative weight. */
    private const array ONLINE_CUSTOMERS = ['35123456' => 40, '20289456121' => 30, '30716485273' => 15, '30502793175' => 15];

    /** Counter sales billed to a registered customer instead of Consumidor Final. */
    private const array COUNTER_CUSTOMERS = ['20289456121' => 4, '30716485273' => 4];

    private Randomizer $random;

    private CarbonImmutable $realNow;

    /** @var Collection<int, Article> */
    private Collection $articles;

    /** @var array<int, string> general list price by article id */
    private array $generalPrices = [];

    /** @var array<int, Supplier> main supplier by article id */
    private array $supplierByArticle = [];

    /** @var array<int, array<int, float>> quantity sold since the last restock, by warehouse and article */
    private array $soldSinceRestock = [];

    /** @var array<int, int> last voucher number used, by supplier id */
    private array $voucherNumbers = [];

    /** @var array<string, list<int>> unpaid simulated invoice ids, by due date */
    private array $invoicesByDueDate = [];

    /** @var Collection<string, PaymentMethod>|null active payment methods by name */
    private ?Collection $paymentMethods = null;

    private ?PaymentMethod $cashPaymentMethod = null;

    /** @var array<string, WebOrder> online orders of the day still to be prepared or delivered */
    private array $placedOrders = [];

    /** @var array<int, CashSession> open cash session by point of sale id */
    private array $openSessions = [];

    public function __construct(
        private OpenCashSession $openCashSession,
        private CloseCashSession $closeCashSession,
        private GetCashSessionExpectedTotals $expectedTotals,
        private OpenSale $openSale,
        private ChangeSaleCustomer $changeSaleCustomer,
        private AddArticleToSale $addArticleToSale,
        private ConfirmSalePayment $confirmSalePayment,
        private DiscardSale $discardSale,
        private AddArticleToCart $addArticleToCart,
        private ClearCart $clearCart,
        private GetOnlineAvailability $onlineAvailability,
        private PlaceWebOrder $placeWebOrder,
        private ReserveWebOrderStock $reserveWebOrderStock,
        private MarkWebOrderAsPaid $markWebOrderAsPaid,
        private StartWebOrderPreparation $startWebOrderPreparation,
        private DeliverWebOrder $deliverWebOrder,
        private CreateSupplierVoucher $createSupplierVoucher,
        private IssuePaymentOrder $issuePaymentOrder,
    ) {}

    /**
     * First simulated day. DatabaseSeeder dates the initial stock load on it, so the kardex
     * opens before the first sale.
     */
    public static function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::today()->subDays(max(0, (int) config('demo.sales_history_days')));
    }

    public function run(): void
    {
        $days = (int) config('demo.sales_history_days');

        if ($days <= 0 || Sale::query()->where('status', SaleStatus::Confirmed)->exists()) {
            return;
        }

        $this->random = new Randomizer(new Mt19937(self::RANDOM_SEED));
        $this->realNow = CarbonImmutable::now();
        $this->loadCatalog();

        $tills = $this->resolveTills();
        $warehouses = Warehouse::query()->whereIn('name', array_keys(self::RESTOCK_BASE_TARGET))->get();
        $onlineCustomers = $this->weightedCustomers(self::ONLINE_CUSTOMERS);
        $counterCustomers = $this->weightedCustomers(self::COUNTER_CUSTOMERS);
        $warehouseUser = $this->staffUser(UserRole::Deposito);
        $purchasingUser = $this->staffUser(UserRole::Compras);
        $pickupBranches = Branch::query()->active()->get();

        try {
            for ($day = self::startsAt(); $day->lte($this->realNow->startOfDay()); $day = $day->addDay()) {
                $events = [];

                if ($day->eq(self::startsAt()) || in_array($day->dayOfWeekIso, self::RESTOCK_WEEKDAYS, true)) {
                    $events[] = [$day->setTime(7, 0), fn () => $this->restock($warehouses, $purchasingUser)];
                }

                if (isset($this->invoicesByDueDate[$day->toDateString()])) {
                    $events[] = [$day->setTime(7, 30), fn () => $this->paySuppliers($day, $purchasingUser)];
                }

                foreach ($tills as $till) {
                    array_push($events, ...$this->tillEvents($day, $till, $counterCustomers));
                }

                array_push($events, ...$this->onlineEvents($day, $days, $onlineCustomers, $pickupBranches, $warehouseUser));

                usort($events, fn (array $a, array $b): int => $a[0] <=> $b[0]);

                // One commit per simulated day instead of one per sale: the Actions' own
                // transactions become savepoints, and seeding takes a fraction of the time.
                DB::transaction(function () use ($events): void {
                    foreach ($events as [$at, $event]) {
                        if ($at->gt($this->realNow)) {
                            break;
                        }

                        Date::setTestNow($at);
                        $event();
                    }

                    $this->closeRemainingSessions();
                });
            }
        } finally {
            Date::setTestNow();
        }
    }

    private function loadCatalog(): void
    {
        $this->articles = Article::query()->active()->with(['unitOfMeasure', 'vatRate'])->orderBy('id')->get();

        $generalList = PriceList::query()->forChannel(PriceListChannel::General)->active()->firstOrFail();
        $this->generalPrices = $generalList->items()->pluck('price', 'article_id')->all();

        $associations = ArticleSupplier::query()->with('supplier')->orderBy('id')->get();

        foreach ($associations as $association) {
            if ($association->supplier->is_active) {
                $this->supplierByArticle[$association->article_id] ??= $association->supplier;
            }
        }

        // Only what can be restocked is sold: an article without a supplier would never have stock.
        $this->articles = $this->articles
            ->filter(fn (Article $article): bool => isset($this->generalPrices[$article->id], $this->supplierByArticle[$article->id]))
            ->values();

        if ($this->articles->isEmpty()) {
            throw new RuntimeException('El historial de ventas necesita artículos activos con proveedor y precio en la Lista General.');
        }
    }

    /**
     * @return list<array{point_of_sale: PointOfSale, cashier: User, opens: string, closes: string, base_sales: int}>
     */
    private function resolveTills(): array
    {
        $tills = [];

        foreach (self::TILLS as $till) {
            $pointOfSale = PointOfSale::query()
                ->where('number', $till['number'])
                ->whereHas('warehouse', fn ($query) => $query->where('name', $till['warehouse']))
                ->with('warehouse')
                ->firstOrFail();

            $cashier = User::query()->firstOrCreate(
                ['email' => $till['cashier_email'].'@'.config('demo.staff_email_domain')],
                [
                    'name' => $till['cashier_name'],
                    'password' => config('demo.staff_password'),
                    'email_verified_at' => now(),
                    'role' => UserRole::Cajero,
                    'branch_id' => Branch::query()->where('name', $till['branch'])->value('id'),
                    'is_active' => true,
                ]
            );

            $tills[] = [
                'point_of_sale' => $pointOfSale,
                'cashier' => $cashier,
                'opens' => $till['opens'],
                'closes' => $till['closes'],
                'base_sales' => $till['base_sales'],
            ];
        }

        return $tills;
    }

    private function staffUser(UserRole $role): User
    {
        return User::query()->where('role', $role)->orderBy('id')->first()
            ?? User::query()->where('role', UserRole::Administrador)->orderBy('id')->firstOrFail();
    }

    /**
     * @param  array<int|string, int>  $weights  by id number (PHP turns numeric keys into ints)
     * @return list<Customer> each customer repeated by its weight
     */
    private function weightedCustomers(array $weights): array
    {
        $idNumbers = array_map(fn (int|string $idNumber): string => (string) $idNumber, array_keys($weights));
        $customers = Customer::query()->whereIn('id_number', $idNumbers)->where('is_active', true)->get()->keyBy('id_number');
        $weighted = [];

        foreach ($weights as $idNumber => $weight) {
            if ($customers->has($idNumber)) {
                array_push($weighted, ...array_fill(0, $weight, $customers->get($idNumber)));
            }
        }

        return $weighted;
    }

    /**
     * Opening, sales and closing of one till on one day.
     *
     * @param  array{point_of_sale: PointOfSale, cashier: User, opens: string, closes: string, base_sales: int}  $till
     * @param  list<Customer>  $counterCustomers
     * @return list<array{0: CarbonImmutable, 1: Closure}>
     */
    private function tillEvents(CarbonImmutable $day, array $till, array $counterCustomers): array
    {
        $opensAt = $day->setTimeFromTimeString($till['opens']);
        $closesAt = $day->setTimeFromTimeString($till['closes']);
        $pointOfSale = $till['point_of_sale'];
        $cashier = $till['cashier'];

        $events = [[$opensAt, fn () => $this->openSession($pointOfSale, $cashier)]];

        $salesCount = (int) round($till['base_sales'] * $this->volumeFactor($day) * $this->random->getFloat(0.75, 1.25));
        $openMinutes = (int) $opensAt->diffInMinutes($closesAt);

        for ($i = 0; $i < $salesCount; $i++) {
            $at = $opensAt->addMinutes($this->random->getInt(5, $openMinutes - 10));
            $customer = $this->random->getInt(1, 100) <= count($counterCustomers) && $counterCustomers !== []
                ? $counterCustomers[$this->random->getInt(0, count($counterCustomers) - 1)]
                : null;

            $events[] = [$at, fn () => $this->counterSale($pointOfSale, $cashier, $customer)];
        }

        $events[] = [$closesAt, fn () => $this->closeSession($pointOfSale)];

        return $events;
    }

    /**
     * Seasonality: busier weekends and a slow growth over the simulated months.
     */
    private function volumeFactor(CarbonImmutable $day): float
    {
        $monthsFromStart = self::startsAt()->diffInDays($day) / 30;

        return self::WEEKDAY_FACTOR[$day->dayOfWeekIso] * (1 + 0.03 * $monthsFromStart);
    }

    private function openSession(PointOfSale $pointOfSale, User $cashier): void
    {
        // Change fund: $20.000 in small bills.
        $this->openSessions[$pointOfSale->id] = $this->openCashSession->handle([
            'point_of_sale_id' => $pointOfSale->id,
            'counts' => [1000 => 10, 500 => 10, 100 => 50],
        ], $cashier->id);
    }

    /**
     * Closes with the movements as declared amounts: cash is counted bill by bill, so the cents
     * that never fit in a bill show up as a small, explained shortage.
     */
    private function closeSession(PointOfSale $pointOfSale): void
    {
        $session = $this->openSessions[$pointOfSale->id] ?? null;

        if ($session === null) {
            return;
        }

        $declarations = [];
        $cashCents = 0;

        foreach ($this->expectedTotals->handle($session) as $total) {
            $paymentMethod = $total['payment_method'];

            if ($paymentMethod->kind === PaymentMethodKind::Cash) {
                $cashCents = $this->moneyToCents($total['expected_amount']);

                continue;
            }

            $declarations[$paymentMethod->id] = [
                'declared_amount' => $total['expected_amount'],
                'batch_reference' => $paymentMethod->kind->requiresBatchReference()
                    ? 'Lote '.str_pad((string) $this->random->getInt(1, 999), 3, '0', STR_PAD_LEFT)
                    : null,
            ];
        }

        $counts = $this->billCount(intdiv($cashCents, 100));
        $countedPesos = array_sum(array_map(fn (int $quantity, int $denomination): int => $quantity * $denomination, $counts, array_keys($counts)));

        $this->closeCashSession->handle($session, [
            'counts' => $counts,
            'declarations' => $declarations,
            'closing_notes' => $countedPesos * 100 === $cashCents ? null : 'Diferencia menor: monedas y centavos que no entran en el conteo de billetes.',
        ]);

        unset($this->openSessions[$pointOfSale->id]);
    }

    /**
     * Today's tills are closed at the current time instead of being left open.
     */
    private function closeRemainingSessions(): void
    {
        foreach (array_keys($this->openSessions) as $pointOfSaleId) {
            Date::setTestNow($this->realNow);
            $this->closeSession($this->openSessions[$pointOfSaleId]->pointOfSale);
        }
    }

    /**
     * Greedy bill count for an amount in pesos; whatever is below the smallest bill stays out.
     *
     * @return array<int, int>
     */
    private function billCount(int $pesos): array
    {
        $counts = [];

        foreach (CashDenomination::cases() as $denomination) {
            $counts[$denomination->value] = intdiv($pesos, $denomination->value);
            $pesos %= $denomination->value;
        }

        return $counts;
    }

    private function counterSale(PointOfSale $pointOfSale, User $cashier, ?Customer $customer): void
    {
        $sale = $this->openSale->handle($cashier);

        if ($customer !== null) {
            $this->changeSaleCustomer->handle($sale, $customer);
        }

        $warehouseId = $pointOfSale->warehouse_id;
        $balances = StockBalance::query()->where('warehouse_id', $warehouseId)->pluck('quantity', 'article_id');

        foreach ($this->pickBasket($this->articles, $this->random->getInt(1, 6)) as $article) {
            $quantity = $this->lineQuantity($article, $customer !== null);

            if ((float) ($balances[$article->id] ?? 0) < $quantity) {
                continue;
            }

            $this->addArticleToSale->handle($sale, ['article_id' => $article->id, 'quantity' => $quantity]);
            $this->soldSinceRestock[$warehouseId][$article->id] = ($this->soldSinceRestock[$warehouseId][$article->id] ?? 0) + $quantity;
        }

        $sale->refresh();

        if (! $sale->items()->exists()) {
            $this->discardSale->handle($sale);

            return;
        }

        Date::setTestNow(now()->addMinutes($this->random->getInt(1, 4)));

        $this->confirmSalePayment->handle($sale, $this->payments($sale->total_amount), $cashier);
    }

    /**
     * Distinct articles for one basket, popular articles more often.
     *
     * @param  Collection<int, Article>  $articles
     * @return list<Article>
     */
    private function pickBasket(Collection $articles, int $size): array
    {
        $weighted = [];

        foreach ($articles as $article) {
            array_push($weighted, ...array_fill(0, 1 + ($article->id * 7) % 5, $article));
        }

        $basket = [];

        for ($attempt = 0; $attempt < $size * 3 && count($basket) < $size; $attempt++) {
            $article = $weighted[$this->random->getInt(0, count($weighted) - 1)];
            $basket[$article->id] = $article;
        }

        return array_values($basket);
    }

    private function lineQuantity(Article $article, bool $isBusinessCustomer): float
    {
        if ($article->allowsDecimalQuantity()) {
            return $this->random->getInt(5, 40) * 0.05;
        }

        $quantity = $this->random->getInt(1, 100) <= 75 ? 1 : $this->random->getInt(2, 4);

        return $isBusinessCustomer ? $quantity * $this->random->getInt(2, 4) : $quantity;
    }

    /**
     * Payment mix of a supermarket till; a few sales are split between cash and debit card.
     *
     * @return list<array{payment_method_id: int, amount: string, tendered_amount?: string}>
     */
    private function payments(string $total): array
    {
        $methods = $this->paymentMethods ??= PaymentMethod::query()->where('is_active', true)->get()->keyBy('name');
        $cash = $this->cashPaymentMethod ??= PaymentMethod::query()->cash()->firstOrFail();
        $totalCents = $this->moneyToCents($total);
        $roll = $this->random->getInt(1, 100);

        if ($roll <= 8 && $totalCents > 200_000 && $methods->has('Tarjeta de Débito')) {
            $cashCents = intdiv($totalCents, 2 * 100_000) * 100_000;

            return [
                ['payment_method_id' => $cash->id, 'amount' => $this->centsToMoney($cashCents)],
                ['payment_method_id' => $methods['Tarjeta de Débito']->id, 'amount' => $this->centsToMoney($totalCents - $cashCents)],
            ];
        }

        $name = match (true) {
            $roll <= 50 => null,
            $roll <= 78 => 'Tarjeta de Débito',
            $roll <= 91 => 'Tarjeta de Crédito',
            $roll <= 97 => 'Mercado Pago',
            default => 'Transferencia Bancaria',
        };

        if ($name === null || ! $methods->has($name)) {
            // Customers hand over the next round amount and get change.
            $tenderedCents = (intdiv($totalCents, 100_000) + 1) * 100_000;

            return [['payment_method_id' => $cash->id, 'amount' => $total, 'tendered_amount' => $this->centsToMoney($tenderedCents)]];
        }

        return [['payment_method_id' => $methods[$name]->id, 'amount' => $total]];
    }

    /**
     * Online orders of one day: placed, reserved and paid at once, prepared within the hour and
     * delivered some hours later. Today's later steps fall after the current time and stay
     * pending, as in a live store.
     *
     * @param  list<Customer>  $customers
     * @param  Collection<int, Branch>  $pickupBranches
     * @return list<array{0: CarbonImmutable, 1: Closure}>
     */
    private function onlineEvents(CarbonImmutable $day, int $totalDays, array $customers, Collection $pickupBranches, User $warehouseUser): array
    {
        if ($customers === [] || $pickupBranches->isEmpty()) {
            return [];
        }

        // The web store grows faster than the counter: from ~1 to ~4 orders a day.
        $progress = self::startsAt()->diffInDays($day) / max(1, $totalDays);
        $ordersCount = (int) round((1 + 3 * $progress) * self::WEEKDAY_FACTOR[$day->dayOfWeekIso] * $this->random->getFloat(0.5, 1.5));
        $events = [];

        for ($i = 0; $i < $ordersCount; $i++) {
            $placedAt = $day->setTime($this->random->getInt(8, 16), $this->random->getInt(0, 59));
            $preparedAt = $placedAt->addMinutes($this->random->getInt(30, 90));
            $deliveredAt = $preparedAt->addMinutes($this->random->getInt(60, 240));
            $customer = $customers[$this->random->getInt(0, count($customers) - 1)];
            $pickupBranch = $this->random->getInt(1, 100) <= 60 ? $pickupBranches[$this->random->getInt(0, $pickupBranches->count() - 1)] : null;
            // The three steps of this order share it through $this->placedOrders; it stays unset
            // when nothing could be placed.
            $key = $day->toDateString().'#'.$i;

            $events[] = [$placedAt, function () use ($customer, $pickupBranch, $key): void {
                $order = $this->placeOnlineOrder($customer, $pickupBranch);

                if ($order !== null) {
                    $this->placedOrders[$key] = $order;
                }
            }];
            $events[] = [$preparedAt, function () use ($key, $warehouseUser): void {
                if (isset($this->placedOrders[$key])) {
                    $this->startWebOrderPreparation->execute($this->placedOrders[$key], $warehouseUser);
                }
            }];
            $events[] = [$deliveredAt, function () use ($key, $warehouseUser): void {
                if (isset($this->placedOrders[$key])) {
                    $this->deliverWebOrder->execute($this->placedOrders[$key]->refresh(), $warehouseUser);
                    unset($this->placedOrders[$key]);
                }
            }];
        }

        return $events;
    }

    private function placeOnlineOrder(Customer $customer, ?Branch $pickupBranch): ?WebOrder
    {
        $this->clearCart->execute($customer);

        $publishable = $this->articles->filter(fn (Article $article): bool => $article->is_online_publishable);
        $availability = $this->onlineAvailability->forArticles($publishable->pluck('id')->all());
        $added = 0;

        foreach ($this->pickBasket($publishable, $this->random->getInt(1, 5)) as $article) {
            $quantity = $this->lineQuantity($article, false);
            $available = (float) ($availability[$article->id]->availableQuantity ?? 0);

            if ($available < $quantity) {
                continue;
            }

            $this->addArticleToCart->execute($customer, $article, $quantity);
            $added++;
        }

        if ($added === 0) {
            return null;
        }

        $order = $pickupBranch !== null
            ? $this->placeWebOrder->execute($customer, DeliveryMethod::Pickup, $pickupBranch)
            : $this->placeWebOrder->execute($customer, DeliveryMethod::Shipping, null, $customer->address ?? 'Domicilio del cliente, Salta');

        $this->reserveWebOrderStock->execute($order);

        $paidAt = now()->addMinutes($this->random->getInt(2, 8));
        Date::setTestNow($paidAt);

        $order = $this->markWebOrderAsPaid->execute($order->refresh(), 'SEED-'.$order->formattedNumber(), $order->total_amount, $paidAt);

        $warehouseId = Warehouse::query()->online()->value('id');

        foreach ($order->items as $item) {
            $this->soldSinceRestock[$warehouseId][$item->article_id] = ($this->soldSinceRestock[$warehouseId][$item->article_id] ?? 0) + (float) $item->quantity;
        }

        return $order;
    }

    /**
     * Brings every warehouse back to its target stock with one remito and one invoice per supplier.
     * The target grows with what was sold since the previous restock, so busy articles never run dry.
     *
     * @param  Collection<int, Warehouse>  $warehouses
     */
    private function restock(Collection $warehouses, User $purchasingUser): void
    {
        foreach ($warehouses as $warehouse) {
            $balances = StockBalance::query()->where('warehouse_id', $warehouse->id)->pluck('quantity', 'article_id');
            $baseTarget = self::RESTOCK_BASE_TARGET[$warehouse->name];
            $itemsBySupplier = [];

            foreach ($this->articles as $article) {
                $supplier = $this->supplierByArticle[$article->id] ?? null;

                if ($supplier === null || ($warehouse->is_online_channel && ! $article->is_online_publishable)) {
                    continue;
                }

                $weighed = $article->allowsDecimalQuantity();
                $sold = $this->soldSinceRestock[$warehouse->id][$article->id] ?? 0;
                $target = max($weighed ? $baseTarget['weighed'] : $baseTarget['units'], $sold * 1.5);
                $missing = $target - (float) ($balances[$article->id] ?? 0);
                $quantity = $weighed ? ceil($missing * 2) / 2 : ceil($missing);

                if ($quantity <= 0) {
                    continue;
                }

                $unitCost = round((float) $this->generalPrices[$article->id] * self::PURCHASE_COST_RATIO, 2);

                $itemsBySupplier[$supplier->id][] = [
                    'article_id' => $article->id,
                    'description' => $article->description,
                    'quantity' => number_format($quantity, 2, '.', ''),
                    'unit_of_measure' => $article->unitOfMeasure->abbreviation ?? $article->unitOfMeasure->name,
                    'unit_price' => number_format($unitCost, 2, '.', ''),
                    'line_total' => number_format(round($quantity * $unitCost, 2), 2, '.', ''),
                ];
            }

            foreach ($itemsBySupplier as $items) {
                $this->receivePurchase($this->supplierByArticle[$items[0]['article_id']], $warehouse, $items, $purchasingUser);
            }

            $this->soldSinceRestock[$warehouse->id] = [];
        }
    }

    /**
     * @param  list<array{article_id: int, description: string, quantity: string, unit_of_measure: string, unit_price: string, line_total: string}>  $items
     */
    private function receivePurchase(Supplier $supplier, Warehouse $warehouse, array $items, User $purchasingUser): void
    {
        $total = $this->centsToMoney(array_sum(array_map(fn (array $item): int => $this->moneyToCents($item['line_total']), $items)));
        $number = str_pad((string) ($this->voucherNumbers[$supplier->id] = ($this->voucherNumbers[$supplier->id] ?? 0) + 1), 8, '0', STR_PAD_LEFT);
        $common = [
            'supplier_id' => $supplier->id,
            'point_of_sale' => self::SUPPLIER_VOUCHER_POINT_OF_SALE,
            'number' => $number,
            'issue_date' => today()->toDateString(),
            'total_amount' => $total,
            'user_id' => $purchasingUser->id,
            'items' => $items,
        ];

        $this->createSupplierVoucher->handle([
            ...$common,
            'type' => SupplierVoucherType::Remito->value,
            'letter' => SupplierVoucherLetter::R->value,
            'due_date' => null,
            'warehouse_id' => $warehouse->id,
            'notes' => "Reposición semanal de {$warehouse->name}.",
        ]);

        $dueDate = today()->addDays(self::SUPPLIER_PAYMENT_TERM_DAYS)->toDateString();

        $invoice = $this->createSupplierVoucher->handle([
            ...$common,
            'type' => SupplierVoucherType::Invoice->value,
            'letter' => ($supplier->tax_condition === SupplierTaxCondition::ResponsibleInscripto ? SupplierVoucherLetter::A : SupplierVoucherLetter::C)->value,
            'due_date' => $dueDate,
            'notes' => "Factura de la reposición de {$warehouse->name}, pago a 30 días.",
        ]);

        $this->invoicesByDueDate[$dueDate][] = $invoice->id;
    }

    /**
     * Pays by bank transfer every simulated invoice due today, one payment order per supplier.
     */
    private function paySuppliers(CarbonImmutable $day, User $purchasingUser): void
    {
        $transfer = PaymentMethod::query()->where('kind', PaymentMethodKind::Transfer)->where('is_active', true)->first();

        if ($transfer === null) {
            return;
        }

        $invoices = SupplierVoucher::query()->whereIn('id', $this->invoicesByDueDate[$day->toDateString()])->get();
        unset($this->invoicesByDueDate[$day->toDateString()]);

        foreach ($invoices->groupBy('supplier_id') as $supplierId => $supplierInvoices) {
            $items = $supplierInvoices
                ->map(fn (SupplierVoucher $invoice): array => ['supplier_voucher_id' => $invoice->id, 'amount_applied' => $invoice->outstandingAmount()])
                ->filter(fn (array $item): bool => $this->moneyToCents($item['amount_applied']) > 0)
                ->values()
                ->all();

            if ($items === []) {
                continue;
            }

            $amount = $this->centsToMoney(array_sum(array_map(fn (array $item): int => $this->moneyToCents($item['amount_applied']), $items)));

            $this->issuePaymentOrder->handle([
                'supplier_id' => (int) $supplierId,
                'date' => $day->toDateString(),
                'notes' => 'Pago a 30 días de las reposiciones.',
                'payment_methods' => [[
                    'payment_method_id' => $transfer->id,
                    'amount' => $amount,
                    'transaction_number' => (string) $this->random->getInt(10_000_000, 99_999_999),
                ]],
                'items' => $items,
            ], $purchasingUser->id);
        }
    }
}
