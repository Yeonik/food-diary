<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The daily target. Every field is optional — the diary works with no goal set,
 * in which case no "remaining" is shown at all. A target is a number to aim at,
 * never a verdict, and the app never suggests lowering it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table): void {
            $table->id();
            $table->double('daily_kcal')->nullable();
            $table->double('protein_g')->nullable();
            $table->double('fat_g')->nullable();
            $table->double('carbs_g')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
