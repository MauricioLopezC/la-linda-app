<?php

namespace App\Http\Controllers\Reporting;

use App\Actions\Reporting\GetDashboardExpenses;
use App\Actions\Reporting\GetDashboardRevenues;
use App\Data\Reporting\DashboardBranchOptionData;
use App\Data\Reporting\DashboardData;
use App\Data\Reporting\DashboardFiltersData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\DashboardFilterRequest;
use App\Models\Organization\Branch;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the management dashboard for the selected period, branch and channel.
     */
    public function index(
        DashboardFilterRequest $request,
        GetDashboardRevenues $getDashboardRevenues,
        GetDashboardExpenses $getDashboardExpenses,
    ): Response {
        $filters = $request->filters();

        $branches = Branch::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        $dashboard = new DashboardData(
            filters: new DashboardFiltersData(...$filters),
            branches: $branches->map(fn (Branch $branch): DashboardBranchOptionData => DashboardBranchOptionData::fromModel($branch))->all(),
            revenues: $getDashboardRevenues->handle($filters),
            expenses: $getDashboardExpenses->handle($filters),
        );

        return Inertia::render('dashboard', $dashboard->toArray());
    }
}
