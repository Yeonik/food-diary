<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\View\View;

/**
 * The accounts on this instance, for the person who administers it.
 *
 * **This is the one screen that reads across everybody deliberately.** Every
 * domain model carries a global scope so that no query can see another person's
 * records; `User` carries none, because an account is not a record belonging to
 * an account. So the roster is a plain query, and what keeps it honest is the
 * gate on the route rather than a scope on the model.
 *
 * What it shows is deliberately thin: a name, an address, whether the account is
 * suspended, and when it joined. Not what anybody has been eating, not how much,
 * not when they last signed in. Administering who may use the instance does not
 * require reading the diaries on it, and this screen is built so that it cannot
 * start to.
 */
class UserAdminController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            // In the order they arrived. The owner is first because the owner
            // was first, not because the list sorts them there.
            'accounts' => User::query()->orderBy('id')->get(),
        ]);
    }
}
