<?php

declare(strict_types=1);

return [

    /*
    | The owner's account, created once by migration on the deploy that removes
    | the old password gate. Without an address the migration refuses before it
    | writes anything, so a deploy that forgot to set it fails and leaves the
    | database untouched rather than producing an instance nobody can enter.
    |
    | Set both on the platform before that deploy; remove OWNER_PASSWORD after
    | the first sign-in. Re-running migrations never rewrites an existing
    | account, so a password changed in the app is not reset by a stale variable.
    */
    'owner' => [
        'email' => env('OWNER_EMAIL'),
        // Secret: read from the environment only, never hard-coded, and never
        // interpolated into a message or a log line.
        'password' => env('OWNER_PASSWORD'),
        'name' => env('OWNER_NAME'),
    ],

    // There is deliberately no "fake recogniser" runtime switch. The fake is a
    // test double, bound only in the test environment; the running app always
    // uses the real recogniser and fails loudly without a key.

    'gemini' => [
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        // `?:`, not a second arg: compose injects an empty GEMINI_MODEL when the
        // var is unset, and an empty string must fall back to the default too.
        'model' => env('GEMINI_MODEL') ?: 'gemini-3.5-flash',
        // Secret: read from the environment only, never hard-coded.
        'key' => env('GEMINI_API_KEY'),
        // Per-attempt timeout in seconds. A photo of a busy model can be slow to
        // answer, so this is generous; recognition retries a couple of times.
        'timeout' => (int) env('GEMINI_TIMEOUT', 60),
    ],

    /*
    | Recognitions one account may ask for in a day. There is one API key on the
    | installation and one bill behind it, and every account spends from it.
    |
    | Counted when a recognition is asked for, not when it succeeds: a failed
    | call costs the key what a good one costs. Zero is read as a deliberate off
    | switch — nobody recognises anything — and not as "no limit", because a
    | misread setting must never be the one that removes the limit.
    */
    'recognition' => [
        'daily_limit' => (int) env('RECOGNITION_DAILY_LIMIT', 25),
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
