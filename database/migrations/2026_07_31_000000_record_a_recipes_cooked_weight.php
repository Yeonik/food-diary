<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The weight of the finished dish, for a recipe.
 *
 * A recipe's per-100 g profile is the batch totals divided by a weight, and
 * until now that weight was the sum of the raw ingredients. But the person
 * weighs the *cooked* dish — rice absorbs water, a roast loses it — so the
 * divisor has to be the cooked weight, and that is a figure only they can
 * supply. This column holds it.
 *
 * **Nullable on purpose, and not because the value is optional.** A recipe with
 * no cooked weight is incomplete: it yields no number at all, rather than
 * falling back to the old divisor (see RecipeCalculator). The column is nullable
 * because the recipes that already exist have no cooked weight and one cannot be
 * invented for them — a made-up weight is exactly the number this whole change
 * exists to stop presenting as verified. So they arrive here null, are shown as
 * needing a cooked weight, and wait for their owner to supply one.
 *
 * Additive, like `suspended_at`: a nullable column with no default writes the
 * table header and rebuilds nothing, so it needs no rehearsal against a copy of
 * the live database. The deploy still takes its backup first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_items', function (Blueprint $blueprint): void {
            // Same type as the per-100 g figures beside it. Grams of the dish as
            // it is eaten, which is a different quantity from any ingredient's.
            $blueprint->double('cooked_weight_g')->nullable()->after('carbs_g_per_100g');
        });
    }

    public function down(): void
    {
        Schema::table('food_items', function (Blueprint $blueprint): void {
            $blueprint->dropColumn('cooked_weight_g');
        });
    }
};
