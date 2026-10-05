<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\OpenCashSession;
use App\Actions\Sales\RegisterCashMovement;
use App\Data\Sales\CashDenominationData;
use App\Data\Sales\CashSessionData;
use App\Data\Sales\CashSessionPointOfSaleData;
use App\Enums\Sales\CashDenomination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreCashMovementRequest;
use App\Http\Requests\Sales\StoreCashSessionRequest;
use App\Models\Sales\CashSession;
use App\Models\Sales\PointOfSale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
}
