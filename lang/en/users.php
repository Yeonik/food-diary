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

    'none' => 'Nobody else yet',
    'none_body' => 'Accounts appear here once somebody registers with an invitation.',
];
