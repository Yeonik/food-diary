<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A recipe's composition: for each ingredient, which library item and how many
 * grams. The ingredient may itself be a recipe (guarded against cycles in
 * RecipeCalculator).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_ingredients', function (Blueprint $table): void {
            $table->id();

            // Deleting a recipe drops its ingredient rows.
            $table->foreignId('recipe_id')
                ->constrained('food_items')
                ->cascadeOnDelete();

            // An item used as an ingredient cannot be deleted out from under the
            // recipe that depends on it — that would corrupt the recipe's maths.
            $table->foreignId('ingredient_id')
                ->constrained('food_items')
                ->restrictOnDelete();

            $table->double('grams');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredients');
    }
};
