<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A second name for a library item, in another language — the same food often
 * has a Russian name on the packaging and an English name in USDA. Storing both
 * lets a photo resolve the item by either name forever, instead of a later
 * single-language lookup missing it and creating a duplicate. Nullable and
 * additive: existing rows stay valid and are backfilled as they are used again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_items', function (Blueprint $table): void {
            $table->string('alt_name')->nullable()->after('name')->index();
        });
    }

    public function down(): void
    {
        Schema::table('food_items', function (Blueprint $table): void {
            $table->dropIndex(['alt_name']);
            $table->dropColumn('alt_name');
        });
    }
};
