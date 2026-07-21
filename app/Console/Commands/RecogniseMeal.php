<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Nutrition\Contracts\FoodRecogniser;
use App\Nutrition\Exceptions\InvalidPhotoException;
use App\Nutrition\Exceptions\RecognitionFailedException;
use App\Nutrition\FoodResolver;
use App\Nutrition\PhotoPreparer;
use Illuminate\Console\Command;

/**
 * The documented manual step that exercises the real Gemini, USDA and Open Food
 * Facts clients against a local photo. CI never runs this; it is how a human
 * confirms the live integrations outside the (network-free) test suite.
 *
 *   php artisan nutrition:recognise path/to/meal.jpg
 */
class RecogniseMeal extends Command
{
    protected $signature = 'nutrition:recognise {path : Path to a local meal photo}';

    protected $description = 'Recognise a meal photo and show how each dish resolves (uses the real APIs).';

    public function handle(PhotoPreparer $preparer, FoodRecogniser $recogniser, FoodResolver $resolver): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("No file at: {$path}");

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
            $this->info(sprintf('%s  (~%d g, confidence %.2f)', $item->name, (int) $item->estimatedGrams, $item->confidence));

            $resolution = $resolver->resolve($item->name, $item->estimatedProfile);

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
