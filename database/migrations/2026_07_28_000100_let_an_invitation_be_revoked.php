<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An invitation that was issued and should not be honoured.
 *
 * Revoking marks the row rather than deleting it, so the owner keeps the record
 * of who was invited and when — deleting would make a mistaken invitation
 * indistinguishable from one that was never sent. A code that has already been
 * spent is not revocable: the account exists, and the way to remove it is to
 * remove the account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invites', function (Blueprint $table): void {
            $table->timestamp('revoked_at')->nullable()->after('used_at');
        });
    }

    public function down(): void
    {
        Schema::table('invites', function (Blueprint $table): void {
            $table->dropColumn('revoked_at');
        });
    }
};
