<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The target the next two migrations point at.
 *
 * On its own this index constrains nothing: `id` is an `INTEGER PRIMARY KEY`,
 * which on SQLite is the rowid itself, so `(id, user_id)` is unique the moment
 * `id` is. It exists because SQLite will only accept a foreign key whose parent
 * columns carry a unique index, and the keys that follow name both columns —
 * a child row has to match not just the item but the item's owner.
 *
 * First, and alone in its own file: without it the two rebuilds after it are
 * refused, and a rebuild that is refused halfway is worse than one that never
 * started.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_items', function (Blueprint $blueprint): void {
            $blueprint->unique(['id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('food_items', function (Blueprint $blueprint): void {
            $blueprint->dropUnique(['id', 'user_id']);
        });
    }
};
