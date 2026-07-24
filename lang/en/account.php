<?php

declare(strict_types=1);

return [
    'delete' => 'Delete this account',
    // Says what happens, once, without arguing about it.
    'delete_explain' => 'Everything goes: entries, library, recipes, weight, goals. It cannot be undone, and nothing is kept.',
    'delete_submit' => 'Delete account',
    'deleted' => 'The account and everything in it have been deleted.',
    'owner_cannot_leave' => 'This is the account that administers the installation, so it cannot be deleted from here.',

    // Read by the suspended person, not about them. It says what is true —
    // access is closed, the diary is not gone — and does not invent an appeal
    // process the application knows nothing about.
    'suspended_title' => 'Access suspended',
    'suspended_body' => 'This account is suspended, so the diary cannot be opened at the moment. Nothing in it has been deleted. Lifting the suspension is done by whoever administers this instance.',
    'suspended_since' => 'Suspended on :date',
];
