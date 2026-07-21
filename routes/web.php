<?php

declare(strict_types=1);

use App\Http\Controllers\AccessController;
use App\Http\Controllers\DiaryController;
use App\Http\Controllers\FoodItemController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\ManualEntryController;
use App\Http\Controllers\MealEntryController;
use App\Http\Controllers\MealPhotoController;
use App\Http\Controllers\PendingLogController;
use App\Http\Controllers\WeightController;
use Illuminate\Support\Facades\Route;

// The optional access gate. These two are reachable while locked; everything
// else is behind the EnsureUnlocked middleware in the web group.
Route::get('/unlock', [AccessController::class, 'show'])->name('unlock.show');
Route::post('/unlock', [AccessController::class, 'unlock'])->name('unlock');

// Diary
Route::get('/', [DiaryController::class, 'index'])->name('diary.index');

// Logging a meal — photo path, manual path, shared confirm.
Route::get('/log/photo', [MealPhotoController::class, 'create'])->name('log.photo');
Route::post('/log/photo', [MealPhotoController::class, 'store'])->name('log.photo.store');
Route::get('/log/manual', [ManualEntryController::class, 'create'])->name('log.manual');
Route::post('/log/manual', [ManualEntryController::class, 'store'])->name('log.manual.store');
Route::get('/log/confirm', [PendingLogController::class, 'show'])->name('log.confirm');
Route::post('/log/confirm', [PendingLogController::class, 'store'])->name('log.confirm.store');

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
