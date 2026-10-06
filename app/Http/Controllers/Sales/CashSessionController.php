<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\CloseCashSession;
use App\Actions\Sales\GetCashSessionExpectedTotals;
use App\Actions\Sales\OpenCashSession;
use App\Actions\Sales\RegisterCashMovement;
use App\Data\Sales\CashDenominationData;
use App\Data\Sales\CashSessionClosingData;
use App\Data\Sales\CashSessionData;
use App\Data\Sales\CashSessionPointOfSaleData;
use App\Enums\Sales\CashDenomination;
use App\Enums\Sales\SaleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CloseCashSessionRequest;
use App\Http\Requests\Sales\StoreCashMovementRequest;
use App\Http\Requests\Sales\StoreCashSessionRequest;
use App\Models\Sales\CashSession;
use App\Models\Sales\PointOfSale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

class CashSessionController extends Controller
{
    /**
     * Redirect to the authenticated user's current open session, or to the opening form.
     */
    public function current(Request $request): RedirectResponse
    {
        $openSession = CashSession::query()
            ->openForUser((int) $request->user()?->id)
            ->first();

        if ($openSession === null) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => 'No tenés un turno de caja abierto. Podés abrirlo a continuación.',
            ]);

            return to_route('sales.cash-sessions.create');
        }

        return to_route('sales.cash-sessions.show', $openSession);
    }

    /**
     * Show the opening count form, unless the user already has an open session.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $openSession = CashSession::query()
            ->openForUser((int) $request->user()?->id)
            ->first();

        if ($openSession !== null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Ya tenés un turno de caja abierto.']);

            return to_route('sales.sales.index');
        }

        $pointsOfSale = PointOfSale::query()
            ->active()
            ->with(['warehouse.branch', 'openCashSession.user'])
            ->orderBy('number')
            ->get();

        return Inertia::render('sales/cash-sessions/create', [
            'pointsOfSale' => CashSessionPointOfSaleData::collect($pointsOfSale),
            'denominations' => array_map(
                fn (CashDenomination $denomination): CashDenominationData => CashDenominationData::fromEnum($denomination),
                CashDenomination::cases(),
            ),
        ]);
    }

    /**
     * Open a cash session with the opening bill count.
     */
    public function store(StoreCashSessionRequest $request, OpenCashSession $action): RedirectResponse
    {
        /** @var array{point_of_sale_id: int|string, counts: array<int|string, int|string>} $data */
        $data = $request->validated();
        $cashSession = $action->handle($data);
        $cashSession->load('pointOfSale');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Caja {$cashSession->pointOfSale->number} abierta con un fondo de $".number_format((float) $cashSession->opening_amount, 2, ',', '.').'.',
        ]);

        return to_route('sales.sales.index');
    }

    /**
     * Display a cash session with its movements and financial summary (HU-058).
     */
    public function show(CashSession $cashSession): Response
    {
        return Inertia::render('sales/cash-sessions/show', [
            'cashSession' => CashSessionData::fromModel($cashSession),
        ]);
    }

    /**
     * Register a cash income or expense in an open cash session (HU-058).
     */
    public function storeMovement(
        StoreCashMovementRequest $request,
        CashSession $cashSession,
        RegisterCashMovement $action,
    ): RedirectResponse {
        /** @var array{type: string, amount: numeric-string|float|int, reason: string} $data */
        $data = $request->validated();
        $movement = $action->handle($cashSession, $data);

        $typeLabel = $movement->type->label();
        $amountFormatted = number_format((float) $movement->amount, 2, ',', '.');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$typeLabel} de \${$amountFormatted} registrado exitosamente.",
        ]);

        return back();
    }

    /**
     * Show the closing count form of the cashier's own open session (HU-060). The count is
     * blind: the form gets no expected amounts.
     */
    public function createClosing(Request $request, CashSession $cashSession): Response|RedirectResponse
    {
        abort_unless($cashSession->user_id === $request->user()?->id, 403);

        if (! $cashSession->isOpen()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'El turno de caja ya está cerrado.']);

            return to_route('sales.cash-sessions.show', $cashSession);
        }

        return Inertia::render('sales/cash-sessions/close', [
            'cashSession' => CashSessionClosingData::fromModel($cashSession),
            'denominations' => array_map(
                fn (CashDenomination $denomination): CashDenominationData => CashDenominationData::fromEnum($denomination),
                CashDenomination::cases(),
            ),
            'openSalesCount' => $cashSession->sales()->where('status', SaleStatus::Open)->count(),
        ]);
    }

    /**
     * Close the session with the closing count and the declared amounts (HU-060), then show
     * its closing summary.
     */
    public function storeClosing(
        CloseCashSessionRequest $request,
        CashSession $cashSession,
        CloseCashSession $action,
    ): RedirectResponse {
        /** @var array{counts: array<int|string, int|string>, declarations?: array<int|string, array{declared_amount?: float|int|string|null, batch_reference?: string|null}>, closing_notes?: string|null} $data */
        $data = $request->validated();
        $action->handle($cashSession, $data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Turno #{$cashSession->id} cerrado. La caja quedó libre para abrir un nuevo turno.",
        ]);

        return to_route('sales.cash-sessions.show', $cashSession);
    }

    /**
     * The closing summary of a closed session as a PDF (HU-060): opening, sales, incomes,
     * expenses, and expected, declared and difference per payment method.
     */
    public function closingPdf(CashSession $cashSession, GetCashSessionExpectedTotals $expectedTotals): HttpResponse
    {
        abort_if($cashSession->isOpen(), 404);

        $cashSession->loadMissing([
            'pointOfSale.warehouse.branch',
            'user',
            'closureLines' => fn ($query) => $query->with('paymentMethod')->orderBy('id'),
            'closingCounts' => fn ($query) => $query->where('quantity', '>', 0)->orderByDesc('denomination'),
        ]);

        $totalsByMethod = collect($expectedTotals->handle($cashSession))
            ->keyBy(fn (array $total): int => $total['payment_method']->id);

        $rows = $cashSession->closureLines->map(fn ($line): array => [
            'payment_method' => $line->paymentMethod->name,
            'batch_reference' => $line->batch_reference,
            'opening_amount' => $totalsByMethod->get($line->payment_method_id)['opening_amount'] ?? '0.00',
            'sales_amount' => $totalsByMethod->get($line->payment_method_id)['sales_amount'] ?? '0.00',
            'income_amount' => $totalsByMethod->get($line->payment_method_id)['income_amount'] ?? '0.00',
            'expense_amount' => $totalsByMethod->get($line->payment_method_id)['expense_amount'] ?? '0.00',
            'expected_amount' => $line->expected_amount,
            'declared_amount' => $line->declared_amount,
            'difference' => $line->difference,
        ])->all();

        $totals = [];

        foreach (['opening_amount', 'sales_amount', 'income_amount', 'expense_amount', 'expected_amount', 'declared_amount', 'difference'] as $column) {
            $totals[$column] = array_reduce($rows, fn (string $sum, array $row): string => bcadd($sum, $row[$column], 2), '0.00');
        }

        $cssPath = resource_path('css/pdf/purchase-order.css');
        $stylesheet = File::exists($cssPath) ? File::get($cssPath) : '';

        $pdf = Pdf::loadView('pdf.sales.cash-session-closing', [
            'cashSession' => $cashSession,
            'rows' => $rows,
            'totals' => $totals,
            'company' => config('company'),
            'stylesheet' => $stylesheet,
        ]);

        return $pdf->stream("Rendicion_Caja_{$cashSession->pointOfSale->number}_Turno_{$cashSession->id}.pdf");
    }
}
