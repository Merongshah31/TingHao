<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class StockMemoryDemoController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('stock-planner.index', ['view' => 'calendar']);
    }
}
