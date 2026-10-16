<?php

namespace App\Http\Controllers\Reporting;

use App\Actions\Reporting\ExportDashboard;
use App\Actions\Reporting\GetDashboardEvolution;
use App\Actions\Reporting\GetDashboardExpenses;
use App\Actions\Reporting\GetDashboardOperationalIndicators;
use App\Actions\Reporting\GetDashboardRevenues;
use App\Data\Reporting\DashboardBranchOptionData;
use App\Data\Reporting\DashboardData;
use App\Data\Reporting\DashboardFiltersData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\DashboardFilterRequest;
use App\Models\Organization\Branch;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    /**
     * Display the management dashboard for the selected period, branch and channel.
     */
    public function index(
        DashboardFilterRequest $request,
        GetDashboardRevenues $getDashboardRevenues,
        GetDashboardExpenses $getDashboardExpenses,
        GetDashboardEvolution $getDashboardEvolution,
        GetDashboardOperationalIndicators $getDashboardOperationalIndicators,
    ): Response {
        $filters = $request->filters();

        $branches = Branch::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        $revenues = $getDashboardRevenues->handle($filters);
        $expenses = $getDashboardExpenses->handle($filters);

        $dashboard = new DashboardData(
            filters: new DashboardFiltersData(...$filters),
            branches: $branches->map(fn (Branch $branch): DashboardBranchOptionData => DashboardBranchOptionData::fromModel($branch))->all(),
            revenues: $revenues,
            expenses: $expenses,
            evolution: $getDashboardEvolution->handle($filters, $revenues, $expenses),
            operational: $getDashboardOperationalIndicators->handle($filters),
        );

        return Inertia::render('dashboard', $dashboard->toArray());
    }

    /**
     * Export the management dashboard according to the requested format (Excel by default).
     */
    public function export(
        DashboardFilterRequest $request,
        ExportDashboard $exportDashboard,
    ): BinaryFileResponse|StreamedResponse {
        return $request->input('format') === 'csv'
            ? $this->exportCsv($request, $exportDashboard)
            : $this->exportExcel($request, $exportDashboard);
    }

    /**
     * Export the management dashboard summary to CSV.
     */
    public function exportCsv(
        DashboardFilterRequest $request,
        ExportDashboard $exportDashboard,
    ): StreamedResponse {
        return $exportDashboard->toCsv($request->filters());
    }

    /**
     * Export the management dashboard summary to Excel (XLSX).
     */
    public function exportExcel(
        DashboardFilterRequest $request,
        ExportDashboard $exportDashboard,
    ): BinaryFileResponse {
        return $exportDashboard->toExcel($request->filters());
    }
}
