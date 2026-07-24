<?php

declare(strict_types=1);

return [
    'title' => 'Accounts',
    // Says what the screen is for, and what it deliberately is not.
    'intro' => 'Everybody with an account on this instance. Suspending closes someone\'s way in and keeps their diary; deleting takes the account and everything in it.',

    'settings_link' => 'Accounts',
    'settings_hint' => 'Who has an account here',
    'open' => 'Open',

    'owner' => 'Administers this instance',
    'you' => 'You',
    'joined' => 'Joined :date',

    'state' => [
        'active' => 'Active',
        'suspended' => 'Suspended',
    ],
    'suspended_on' => 'Suspended :date',

    'suspend' => 'Suspend',
    'restore' => 'Lift suspension',
    'suspended_flash' => ':name is suspended. Their diary is untouched.',
    'restored_flash' => ':name can sign in again.',
    // Not a scolding: it says what would happen, which is the reason.
    'cannot_act_on_yourself' => 'This is your own account. Suspending or deleting it would leave nobody able to administer this instance.',

    'delete' => 'Delete',
    'delete_title' => 'Delete :name',
    // Names the account, says it is permanent, and does not soften it.
    'delete_explain' => 'This removes the account and everything in it. It cannot be undone, and nothing is kept.',
    'delete_nothing' => 'This account holds no records.',
    'delete_confirm_label' => 'Type :email to confirm',
    'delete_submit' => 'Delete this account',
    'delete_email_mismatch' => 'That is not this account\'s address, so nothing has been deleted.',
    'deleted_flash' => ':name and everything in that account have been deleted.',

    'holds' => [
        'meal_entries' => ':count logged entries',
        'food_items' => ':count library items',
        'food_item_aliases' => ':count remembered names',
        'recipe_ingredients' => ':count recipe lines',
        'weight_entries' => ':count weight readings',
        'goals' => ':count goal',
        'recognitions' => ':count recognitions',
    ],

    'none' => 'Nobody else yet',
    'none_body' => 'Accounts appear here once somebody registers with an invitation.',
];
