<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * History — kcal per day, weight trend and macro split over a chosen range.
 * The aggregation and the hand-rolled SVG charts arrive with the charts pass;
 * this screen's shell is wired first so the navigation resolves.
 */
class HistoryController extends Controller
{
    public function index(): View
    {
        return view('history.index');
    }
}
