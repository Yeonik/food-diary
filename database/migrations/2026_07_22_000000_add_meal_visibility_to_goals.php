<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which meals appear on the Day screen. This is display-only: turning a meal
 * off hides its section, it never deletes anything and entries already logged
 * in that meal still count towards the day's totals. Additive and defaulted on,
 * so existing data is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table): void {
            $table->boolean('show_breakfast')->default(true);
            $table->boolean('show_lunch')->default(true);
            $table->boolean('show_dinner')->default(true);
            $table->boolean('show_snack')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table): void {
            $table->dropColumn(['show_breakfast', 'show_lunch', 'show_dinner', 'show_snack']);
        });
    }
};
