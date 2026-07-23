<?php

declare(strict_types=1);

namespace App\Nutrition\Recognisers;

use App\Nutrition\Contracts\FoodRecogniser;
use App\Nutrition\PreparedPhoto;
use App\Nutrition\RecognisedItem;
use App\Nutrition\RecognitionQuota;

/**
 * The daily quota, in front of whichever recogniser is really doing the work.
 *
 * A decorator rather than a check in the controller, for the same reason the
 * `auth` middleware wraps the route group rather than each route: the quota then
 * applies because of where the call goes, not because the caller remembered. A
 * second path to recognition added later is metered without anybody thinking
 * about it.
 *
 * The claim is taken before the call, so a provider that fails still costs the
 * person an allowance — see {@see RecognitionQuota} for why that is the choice.
 */
final class MeteredRecogniser implements FoodRecogniser
{
    public function __construct(
        /**
         * Readable on purpose: the test that holds this project's central
         * promise — that CI reaches no network and needs no key — has to be able
         * to see which recogniser is really behind the meter.
         */
        public readonly FoodRecogniser $inner,
        private readonly RecognitionQuota $quota,
    ) {}

    /**
     * @return list<RecognisedItem>
     */
    public function recognise(PreparedPhoto $photo): array
    {
        $this->quota->claimOne();

        return $this->inner->recognise($photo);
    }
}
