<?php

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\OpenCashSession;
use App\Data\Sales\CashDenominationData;
use App\Data\Sales\CashSessionPointOfSaleData;
use App\Enums\Sales\CashDenomination;
use App\Http\Controllers\Controller;
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
     * Show the opening count form, unless the user already has an open session.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if (CashSession::query()->openForUser((int) $request->user()?->id)->exists()) {
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
}
