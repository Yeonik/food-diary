<?php

declare(strict_types=1);

return [
    'title' => 'Invitations',
    'intro' => 'An account can only be created with a code from here.',
    'issue' => 'Create an invitation',
    'issued' => 'The code',
    'issued_once' => 'Shown once. It is not stored, so it cannot be shown again — if it is lost, revoke it and create another.',
    'issued_on' => 'Issued :date',
    'revoke' => 'Revoke',
    'revoked' => 'Invitation revoked.',
    'not_revocable' => 'That invitation has already been used or revoked.',
    'none' => 'No invitations yet',
    'none_body' => 'Create one when somebody should be able to join.',

    'state' => [
        'open' => 'Not used yet',
        'spent' => 'Used',
        'revoked' => 'Revoked',
        'expired' => 'Expired',
    ],

    'recognitions_today' => 'Recognitions today',
    'recognitions_hint' => 'Everybody, on the shared key. :limit per account per day.',

    'open' => 'Open',
    'settings_link' => 'Invitations',
    'settings_hint' => 'Who can create an account',
];
