<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Nutrition\MealLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Logging without a photo: search the library, USDA and Open Food Facts by name
 * and hand the candidates to the same confirm screen the photo path uses.
 */
class ManualEntryController extends Controller
{
    public function create(): View
    {
        return view('log.manual');
    }

    public function store(Request $request, MealLogService $log): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $request->session()->put(PendingLogController::SESSION_KEY, [
            'photo' => null,
            'items' => [$log->pendingForName($validated['name'])],
        ]);

        return redirect()->route('log.confirm');
    }
}
