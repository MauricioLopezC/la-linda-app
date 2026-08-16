<?php

namespace App\Http\Controllers\Organization;

use App\Actions\Organization\CreateBranch;
use App\Actions\Organization\ToggleBranchStatus;
use App\Actions\Organization\UpdateBranch;
use App\Data\Organization\BranchData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreBranchRequest;
use App\Http\Requests\Organization\UpdateBranchRequest;
use App\Models\Organization\Branch;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    /**
     * Display a listing of branches.
     */
    public function index(): Response
    {
        $branches = Branch::query()->orderBy('name')->get();

        return Inertia::render('organization/branches/index', [
            'branches' => BranchData::collect($branches),
        ]);
    }

    /**
     * Store a newly created branch.
     */
    public function store(StoreBranchRequest $request, CreateBranch $action): RedirectResponse
    {
        $action->handle($request->validated());

        return back()->with('success', 'Sucursal creada correctamente.');
    }

    /**
     * Update the specified branch.
     */
    public function update(UpdateBranchRequest $request, Branch $branch, UpdateBranch $action): RedirectResponse
    {
        $action->handle($branch, $request->validated());

        return back()->with('success', 'Sucursal actualizada correctamente.');
    }

    /**
     * Toggle the active status of the specified branch.
     */
    public function toggleStatus(Branch $branch, ToggleBranchStatus $action): RedirectResponse
    {
        $action->handle($branch);

        return back()->with('success', 'Estado de la sucursal actualizado.');
    }
}
