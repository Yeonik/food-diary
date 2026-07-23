<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per recognition asked for, so a day's usage is a count.
 *
 * Deliberately a row rather than a counter on the account. A counter has to be
 * reset by something — a scheduled job, a cron, a check on read — and every one
 * of those is a moving part that can fail quietly and either lock somebody out
 * or let the limit go. Rows expire by themselves: yesterday's simply stop
 * matching today's date.
 *
 * It holds nothing but who and when. There is no photo here, no dish, no
 * outcome — the quota needs to know how many, and knowing more would be keeping
 * a record of what somebody ate in a second place.
 *
 * A user's table like any other: scoped by the owner trait, and it goes when the
 * account goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recognitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // The only query this table has: how many for this person since
            // midnight.
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recognitions');
    }
};
