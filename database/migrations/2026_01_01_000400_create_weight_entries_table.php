<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A weight log — one reading per day. It is a log and a line, nothing more:
 * no BMI verdict, no target-weight nagging, no commentary on the trend.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weight_entries', function (Blueprint $table): void {
            $table->id();
            $table->date('recorded_on')->unique();
            $table->double('weight_kg');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weight_entries');
    }
};
