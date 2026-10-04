<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreHomeController extends Controller
{
    /**
     * Display the online store home page.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('ecommerce/index');
    }
}
