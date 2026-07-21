<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A logged meal. The nutrient columns here are an immutable SNAPSHOT of the
 * absolute totals at the moment of logging — not a live reference to a library
 * item. Correcting that item, or editing a recipe, later must never rewrite
 * last month's totals, so the numbers are copied in, not looked up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_entries', function (Blueprint $table): void {
            $table->id();
            $table->dateTime('logged_at')->index();
            $table->string('meal'); // MealType
            $table->string('name');
            $table->double('grams');

            // Frozen absolute totals for this portion.
            $table->double('kcal');
            $table->double('protein_g');
            $table->double('fat_g');
            $table->double('carbs_g');

            // Which source answered, snapshotted. `estimated` is never verified.
            $table->string('source'); // NutrientSource

            // Provenance link only. Nullable and null-on-delete: deleting the
            // source library item leaves this historical entry intact.
            $table->foreignId('food_item_id')
                ->nullable()
                ->constrained('food_items')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_entries');
    }
};
