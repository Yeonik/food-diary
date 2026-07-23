<?php

declare(strict_types=1);

return [
    'title' => 'Photograph a meal',
    'privacy' => "The photo goes to Google's Gemini API for recognition. Its EXIF metadata (including GPS) is stripped before it leaves this machine, and the file is deleted once you confirm the entry.",
    'wait' => 'Recognition usually takes a few seconds. When the model is busy it retries, so it can take up to two minutes — the page waits, that is not an error.',
    'field' => 'Meal photo',
    'submit' => 'Recognise',
    // Says which limit and when it lifts. Not "try again later" — there is
    // nothing to retry, and nothing is wrong with the recogniser.
    'limit_reached' => 'You have used all :limit recognitions for today. The allowance starts again tomorrow — a meal can still be logged by hand or by barcode.',
];
