<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Names a library item has also been recognised by. A vision model phrases the
 * same package differently from photo to photo, so matching a single stored name
 * is brittle. Each time the user confirms a recognised dish against a library
 * item, the phrasing that found it is remembered here, and future lookups match
 * against these too — the item accrues the ways it is actually seen.
 *
 * Only confirmed phrasings are stored, never every recognition, so a bad guess
 * cannot start pulling in the wrong product.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_item_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('food_item_id')->constrained()->cascadeOnDelete();
            $table->string('name')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_item_aliases');
    }
};
