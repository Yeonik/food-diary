<?php

declare(strict_types=1);

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\DiaryController;
use App\Http\Controllers\FoodItemController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ManualEntryController;
use App\Http\Controllers\MealEntryController;
use App\Http\Controllers\MealPhotoController;
use App\Http\Controllers\PendingLogController;
use App\Http\Controllers\RecipeIngredientController;
use App\Http\Controllers\UserAdminController;
use App\Http\Controllers\WeightController;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Route;

/*
| Everything below needs an account. Sign-in, sign-out and registration are
| registered by Fortify, not here — see config/fortify.php.
|
| The `auth` middleware wraps the whole group rather than route by route: a route
| added later is protected because of where it lives, not because somebody
| remembered. There is no public page in this application.
*/
Route::middleware('auth')->group(function (): void {

    // Diary
    Route::get('/', [DiaryController::class, 'index'])->name('diary.index');

    // History — charts over a chosen range.
    Route::get('/history', [HistoryController::class, 'index'])->name('history.index');

    // Logging a meal — photo path, manual path, shared confirm.
    Route::get('/log/photo', [MealPhotoController::class, 'create'])->name('log.photo');
    Route::post('/log/photo', [MealPhotoController::class, 'store'])->name('log.photo.store');
    Route::get('/log/manual', [ManualEntryController::class, 'create'])->name('log.manual');
    Route::post('/log/manual', [ManualEntryController::class, 'store'])->name('log.manual.store');
    Route::get('/log/confirm', [PendingLogController::class, 'show'])->name('log.confirm');
    Route::post('/log/confirm', [PendingLogController::class, 'store'])->name('log.confirm.store');

    // Barcode path — scan or type a code, resolve one Open Food Facts product,
    // confirm a weight, log. Two steps, the product held in the session between.
    Route::get('/log/barcode', [BarcodeController::class, 'create'])->name('log.barcode');
    Route::post('/log/barcode', [BarcodeController::class, 'lookup'])->name('log.barcode.lookup');
    Route::get('/log/barcode/confirm', [BarcodeController::class, 'confirm'])->name('log.barcode.confirm');
    Route::post('/log/barcode/confirm', [BarcodeController::class, 'store'])->name('log.barcode.confirm.store');

    // Entries
    Route::get('/entries/{entry}/edit', [MealEntryController::class, 'edit'])->name('entries.edit');
    Route::patch('/entries/{entry}', [MealEntryController::class, 'update'])->name('entries.update');
    Route::delete('/entries/{entry}', [MealEntryController::class, 'destroy'])->name('entries.destroy');

    // Personal library
    Route::get('/library', [FoodItemController::class, 'index'])->name('library.index');
    Route::get('/library/create', [FoodItemController::class, 'create'])->name('library.create');
    Route::post('/library', [FoodItemController::class, 'store'])->name('library.store');
    Route::get('/library/recipes/create', [FoodItemController::class, 'createRecipe'])->name('library.recipe.create');
    Route::post('/library/recipes', [FoodItemController::class, 'storeRecipe'])->name('library.recipe.store');

    // Building a recipe from database ingredients — a round trip through the
    // session, not a live search, because there is no XHR in this application.
    // The recipe being assembled is held in the session between these steps.
    // The "find ingredient" button lives inside the recipe form and submits it,
    // so it arrives with whatever verb that form uses — POST for a new recipe,
    // a spoofed PATCH for an edit. Both mean the same thing here: capture the
    // form and search. The verb carries no meaning at this endpoint.
    Route::match(['post', 'patch'], '/library/recipes/ingredients/search', [RecipeIngredientController::class, 'search'])->name('library.recipe.ingredient.search');
    Route::get('/library/recipes/ingredients/choose', [RecipeIngredientController::class, 'choose'])->name('library.recipe.ingredient.choose');
    Route::post('/library/recipes/ingredients/add', [RecipeIngredientController::class, 'add'])->name('library.recipe.ingredient.add');
    Route::post('/library/recipes/ingredients/cancel', [RecipeIngredientController::class, 'cancel'])->name('library.recipe.ingredient.cancel');
    Route::get('/library/recipes/{item}/edit', [FoodItemController::class, 'editRecipe'])->name('library.recipe.edit');
    Route::patch('/library/recipes/{item}', [FoodItemController::class, 'updateRecipe'])->name('library.recipe.update');
    Route::get('/library/{item}/edit', [FoodItemController::class, 'edit'])->name('library.edit');
    Route::patch('/library/{item}', [FoodItemController::class, 'update'])->name('library.update');
    Route::post('/library/{item}/merge', [FoodItemController::class, 'merge'])->name('library.merge');
    Route::delete('/library/{item}', [FoodItemController::class, 'destroy'])->name('library.destroy');

    // Weight
    Route::get('/weight', [WeightController::class, 'index'])->name('weight.index');
    Route::post('/weight', [WeightController::class, 'store'])->name('weight.store');
    Route::delete('/weight/{entry}', [WeightController::class, 'destroy'])->name('weight.destroy');

    // Goal
    Route::get('/goal', [GoalController::class, 'edit'])->name('goal.edit');
    Route::patch('/goal', [GoalController::class, 'update'])->name('goal.update');

    // Leaving. Behind the same `auth` group as everything else — a session is
    // what says whose account this is.
    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');

    // Invitations — the owner's, and only the owner's. The gate wraps the group
    // rather than each route, for the same reason `auth` wraps everything above:
    // a route added here later is behind it because of where it lives.
    Route::middleware('can:'.AppServiceProvider::ADMINISTER_INVITES)->group(function (): void {
        Route::get('/invites', [InviteController::class, 'index'])->name('invites.index');
        Route::post('/invites', [InviteController::class, 'store'])->name('invites.store');
        Route::delete('/invites/{invite}', [InviteController::class, 'destroy'])->name('invites.destroy');
    });

    // The accounts that already exist — a separate ability from invitations,
    // because deciding who may join and reaching into an account that is already
    // here are different powers. Same shape as the group above: the gate wraps
    // the group, so a route added here later is behind it by where it lives.
    Route::middleware('can:'.AppServiceProvider::ADMINISTER_ACCOUNTS)->group(function (): void {
        Route::get('/users', [UserAdminController::class, 'index'])->name('users.index');
        Route::post('/users/{account}/suspension', [UserAdminController::class, 'suspend'])->name('users.suspend');
        Route::delete('/users/{account}/suspension', [UserAdminController::class, 'restore'])->name('users.restore');
        Route::get('/users/{account}/deletion', [UserAdminController::class, 'confirmDelete'])->name('users.delete');
        Route::delete('/users/{account}', [UserAdminController::class, 'destroy'])->name('users.destroy');
    });

});

// Interface language: a cookie and a redirect, no user data, so a guest screen
// can carry the switch too.
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');
