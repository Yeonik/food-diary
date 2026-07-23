<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Nutrition\Contracts\FoodRecogniser;
use App\Nutrition\Exceptions\InvalidPhotoException;
use App\Nutrition\Exceptions\RecognitionFailedException;
use App\Nutrition\FoodResolver;
use App\Nutrition\PhotoPreparer;
use App\Nutrition\SearchTerms;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * The documented manual step that exercises the real Gemini, USDA and Open Food
 * Facts clients against a local photo. CI never runs this; it is how a human
 * confirms the live integrations outside the (network-free) test suite.
 *
 *   php artisan nutrition:recognise path/to/meal.jpg
 */
class RecogniseMeal extends Command
{
    protected $signature = 'nutrition:recognise
        {path : Path to a local meal photo}
        {--as= : The account whose personal library to resolve against}';

    protected $description = 'Recognise a meal photo and show how each dish resolves (uses the real APIs).';

    /**
     * Signs the command in as the account named by --as, or as the only account
     * there is. Returns false when the answer would have to be a guess.
     */
    private function actAsSomebody(): bool
    {
        $email = $this->option('as');

        if (is_string($email) && trim($email) !== '') {
            $user = User::query()->where('email', Str::lower(trim($email)))->first();

            if ($user === null) {
                $this->error("No account with the address {$email}.");

                return false;
            }
        } else {
            $accounts = User::query()->orderBy('id')->limit(2)->get();

            if ($accounts->count() > 1) {
                $this->error('There is more than one account here; say which with --as=someone@example.com.');

                return false;
            }

            $user = $accounts->first();
        }

        if ($user === null) {
            // Worth running anyway: this command exists to exercise the real
            // Gemini, USDA and Open Food Facts clients, and those need no
            // account. Only the personal library tier is unavailable.
            $this->warn('No accounts exist, so the personal library tier will be empty.');

            return true;
        }

        Auth::login($user);
        $this->line("Resolving against {$user->email}'s library.");

        return true;
    }

    public function handle(PhotoPreparer $preparer, FoodRecogniser $recogniser, FoodResolver $resolver): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("No file at: {$path}");

            return self::FAILURE;
        }

        // The personal library is somebody's library, and there is no session
        // out here to say whose. Acting as a named person is how the whole
        // resolver stack — which reads through the same scope a request does —
        // gets an answer at all; without one, tier one is simply empty.
        if (! $this->actAsSomebody()) {
            return self::FAILURE;
        }

        try {
            $prepared = $preparer->prepare($path, storage_path('app/private/photos'));
            $items = $recogniser->recognise($prepared);
        } catch (InvalidPhotoException|RecognitionFailedException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            if (isset($prepared)) {
                @unlink($prepared->path);
            }
        }

        if ($items === []) {
            $this->warn('Nothing was recognised.');

            return self::SUCCESS;
        }

        foreach ($items as $item) {
            $this->line('');
            $label = $item->nativeName !== null ? sprintf('%s / %s', $item->nativeName, $item->name) : $item->name;
            $this->info(sprintf('%s  (~%d g, confidence %.2f)', $label, (int) $item->estimatedGrams, $item->confidence));

            $resolution = $resolver->resolve(new SearchTerms($item->name, $item->nativeName), $item->estimatedProfile);

            foreach ($resolution->candidates() as $match) {
                $this->line(sprintf(
                    '  [%s] %s — %d kcal / 100 g',
                    $match->source()->label(),
                    $match->description,
                    (int) round($match->profile->kcal),
                ));
            }

            foreach ($resolution->notices as $notice) {
                $this->warn('  '.$notice->message);
            }
        }

        return self::SUCCESS;
    }
}
