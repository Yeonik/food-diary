<?php

declare(strict_types=1);

return [

    /*
    | A single optional access password for this self-hosted instance. Leave it
    | unset and the diary is open (the default for a machine only you can reach);
    | set it and every page requires unlocking once per session.
    */
    'access_password' => env('APP_ACCESS_PASSWORD'),

    // There is deliberately no "fake recogniser" runtime switch. The fake is a
    // test double, bound only in the test environment; the running app always
    // uses the real recogniser and fails loudly without a key.

    'gemini' => [
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        // Secret: read from the environment only, never hard-coded.
        'key' => env('GEMINI_API_KEY'),
    ],

    'usda' => [
        'base_url' => env('USDA_BASE_URL', 'https://api.nal.usda.gov/fdc/v1'),
        // Free key from api.data.gov. Sent as a header, never in a URL.
        'key' => env('USDA_API_KEY'),
    ],

    'open_food_facts' => [
        'base_url' => env('OFF_BASE_URL', 'https://world.openfoodfacts.org'),
        // The project's terms ask for a descriptive User-Agent.
        'user_agent' => env('OFF_USER_AGENT', 'food-diary/1.0 (self-hosted)'),
    ],

    'photo' => [
        'max_dimension' => (int) env('PHOTO_MAX_DIMENSION', 1600),
        // Delete the stored photo once the entry is confirmed.
        'delete_after_confirm' => (bool) env('PHOTO_DELETE_AFTER_CONFIRM', true),
        'disk' => env('PHOTO_DISK', 'local'),
        'directory' => env('PHOTO_DIRECTORY', 'photos'),
    ],

];
