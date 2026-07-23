<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registration is by invitation, and this is the ledger of invitations.
 *
 * The code itself is not here. Only a SHA-256 of it is stored, so the table can
 * be read — in a backup, in a support session, by anybody who reaches the
 * database — without handing over a working key to an account. The code is shown
 * once, at the moment it is created, and is not recoverable afterwards: an
 * invitation that is lost is revoked and reissued, which is cheaper than a
 * column of live secrets.
 *
 * SHA-256 rather than bcrypt on purpose. Bcrypt salts each hash, so the same
 * code hashes differently every time and could never be looked up — the check
 * would have to walk every row. That is the right trade for a password, which is
 * short and human-chosen; a code here is 32 characters of cryptographic
 * randomness, which is not guessable from its digest at any budget.
 *
 * `used_at` is what makes a code single-use, and it is set by a conditional
 * update rather than after a read — see the model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invites', function (Blueprint $table): void {
            $table->id();

            // Unique, so the same code cannot exist twice even by accident, and
            // indexed by consequence: the only lookup this table has is by hash.
            $table->string('token_hash', 64)->unique();

            // Who issued it and who spent it. Both survive the account going
            // away: an invitation is a record of something that happened, and
            // losing the name should not turn a spent code back into a live one.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invites');
    }
};
