<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Nutrition\Contracts\FoodRecogniser;
use App\Nutrition\Exceptions\InvalidPhotoException;
use App\Nutrition\Exceptions\RecognitionFailedException;
use App\Nutrition\MealLogService;
use App\Nutrition\PhotoPreparer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The photo path into a log: upload → prepare (EXIF stripped, validated by
 * content, stored under a generated name outside the web root) → recognise →
 * hand off to the shared confirm screen.
 */
class MealPhotoController extends Controller
{
    public function create(): View
    {
        return view('log.photo');
    }

    public function store(
        Request $request,
        PhotoPreparer $preparer,
        FoodRecogniser $recogniser,
        MealLogService $log,
    ): RedirectResponse {
        $request->validate([
            // Validated as an image here, and again by content in the preparer.
            'photo' => ['required', 'file', 'image', 'max:12288'],
        ]);

        $file = $request->file('photo');

        try {
            // The client filename is never touched; the preparer generates its own.
            $prepared = $preparer->prepare($file->getPathname(), storage_path('app/private/photos'));
            $items = $recogniser->recognise($prepared);
        } catch (InvalidPhotoException $e) {
            return back()->withErrors(['photo' => $e->getMessage()]);
        } catch (RecognitionFailedException $e) {
            if (isset($prepared)) {
                @unlink($prepared->path);
            }

            return back()->withErrors(['photo' => $e->getMessage()]);
        }

        $request->session()->put(PendingLogController::SESSION_KEY, [
            'photo' => $prepared->path,
            'items' => $log->pendingForRecognised($items),
        ]);

        return redirect()->route('log.confirm');
    }
}
