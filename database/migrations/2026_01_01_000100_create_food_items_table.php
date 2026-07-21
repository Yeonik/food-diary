<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The personal library. A row is either a `direct` item that carries its own
 * per-100 g profile, or a `recipe` whose profile is computed from its
 * ingredient rows (the per-100 g columns stay null for those).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->index();
            $table->string('kind'); // FoodItemKind: direct | recipe

            // Where a direct item's numbers originally came from (ProfileOrigin:
            // manual | usda | open_food_facts). This is provenance metadata and
            // is distinct from the resolution tier — as a library item, its
            // resolved source is always personal_library. Null for recipes.
            $table->string('origin')->nullable();
            // Stable id at the origin source, e.g. USDA fdcId or OFF barcode.
            $table->string('external_id')->nullable();

            // Per-100 g profile — populated for direct items, null for recipes.
            $table->double('kcal_per_100g')->nullable();
            $table->double('protein_g_per_100g')->nullable();
            $table->double('fat_g_per_100g')->nullable();
            $table->double('carbs_g_per_100g')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_items');
    }
};
