<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Recognition;
use App\Models\User;
use App\Nutrition\Contracts\FoodRecogniser;
use App\Nutrition\Exceptions\RecognitionFailedException;
use App\Nutrition\PreparedPhoto;
use App\Nutrition\Recognisers\MeteredRecogniser;
use App\Nutrition\RecognitionQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A day's recognitions, per account.
 *
 * The quota is not about fairness between people — it is about the one API key
 * the installation has, which every account spends from. So the count is per
 * person and the refusal happens before the provider is called.
 */
class RecognitionQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['nutrition.recognition.daily_limit' => 2]);

        Http::fake([
            'api.nal.usda.gov/*' => Http::response(['foods' => []]),
            'world.openfoodfacts.org/*' => Http::response(['products' => []]),
        ]);
    }

    protected function tearDown(): void
    {
        foreach (glob(storage_path('app/private/photos').'/*') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function recognise(): TestResponse
    {
        return $this->from(route('log.photo'))->post(route('log.photo.store'), [
            'photo' => UploadedFile::fake()->image('meal.jpg', 240, 180),
        ]);
    }

    /** The message the person is left holding, in the words they are shown. */
    private function photoError(): string
    {
        $errors = session('errors');

        if ($errors instanceof ViewErrorBag) {
            return (string) $errors->getBag('default')->first('photo');
        }

        // Flashed, it is a plain array by the time it is read back.
        $messages = is_array($errors) ? ($errors['default']['messages']['photo'] ?? []) : [];

        return is_array($messages) ? (string) ($messages[0] ?? '') : '';
    }

    public function test_the_recognition_after_the_last_one_is_refused(): void
    {
        $this->signIn();

        $this->recognise()->assertRedirect(route('log.confirm'));
        $this->recognise()->assertRedirect(route('log.confirm'));

        $this->recognise()->assertRedirect(route('log.photo'))->assertSessionHasErrors('photo');

        // Two asked for, two recorded — the refused one did not go through and
        // did not add to the count either.
        $this->assertSame(2, Recognition::query()->count());
    }

    public function test_the_refusal_says_which_limit_was_reached_and_when_it_lifts(): void
    {
        $this->signIn();
        $this->recognise();
        $this->recognise();

        $this->recognise();
        $message = (string) $this->photoError();

        // The real reason, in the person's own terms: which allowance, and that
        // it comes back. Nothing about the recogniser, which was never asked.
        $this->assertStringContainsString('2', $message);
        $this->assertStringContainsString(__('photo.limit_reached', ['limit' => 2]), $message);
    }

    public function test_the_refusal_does_not_read_like_the_recogniser_failing(): void
    {
        // The point of a separate message. Somebody told "the recogniser could
        // not be reached" when their own allowance is spent will refresh, retry,
        // check their connection, and none of it can help.
        $this->signIn();
        $this->recognise();
        $this->recognise();

        $this->recognise();
        $limitMessage = (string) $this->photoError();

        // Now the other kind of failure, with the allowance untouched.
        config(['nutrition.recognition.daily_limit' => 10]);
        $this->app->bind(FoodRecogniser::class, fn (): FoodRecogniser => new MeteredRecogniser(
            new class implements FoodRecogniser
            {
                public function recognise(PreparedPhoto $photo): array
                {
                    throw new RecognitionFailedException('The recogniser could not be reached.');
                }
            },
            $this->app->make(RecognitionQuota::class),
        ));

        $this->recognise();
        $failureMessage = (string) $this->photoError();

        $this->assertSame(__('photo.limit_reached', ['limit' => 2]), $limitMessage);
        $this->assertSame('The recogniser could not be reached.', $failureMessage);
        $this->assertNotSame($failureMessage, $limitMessage,
            'A spent allowance and an unreachable recogniser say the same thing.');
    }

    public function test_somebody_elses_recognitions_do_not_count_towards_mine(): void
    {
        $them = User::factory()->create();
        $this->actingAs($them);
        $this->recognise();
        $this->recognise();

        // Their allowance is spent; mine is untouched.
        $this->actingAs(User::factory()->create());

        $this->recognise()->assertRedirect(route('log.confirm'));
        $this->recognise()->assertRedirect(route('log.confirm'));
        $this->recognise()->assertSessionHasErrors('photo');

        $this->assertSame(4, DB::table('recognitions')->count());
        $this->assertSame(2, Recognition::ownedBy($them)->count());
    }

    public function test_a_recognition_counts_when_it_is_asked_for_and_not_when_it_works(): void
    {
        // The deliberate choice, pinned so it cannot drift into the other one by
        // accident: a call the provider fails still spends the allowance,
        // because it costs the key exactly what a good call costs.
        $this->signIn();

        $this->app->bind(FoodRecogniser::class, fn (): FoodRecogniser => new MeteredRecogniser(
            new class implements FoodRecogniser
            {
                public function recognise(PreparedPhoto $photo): array
                {
                    throw new RecognitionFailedException('The recogniser returned an error.');
                }
            },
            $this->app->make(RecognitionQuota::class),
        ));

        $this->recognise()->assertSessionHasErrors('photo');

        $this->assertSame(1, Recognition::query()->count(),
            'A failed call did not count, so a failing provider could be called without limit.');
    }

    public function test_yesterdays_recognitions_do_not_count_towards_today(): void
    {
        // The reason the quota is rows and not a counter: nothing has to reset
        // it, so nothing can fail to.
        $user = $this->signIn();

        Recognition::query()->create([])->forceFill([
            'created_at' => now()->subDay(),
        ])->save();

        $this->assertSame(1, Recognition::ownedBy($user)->count());
        $this->assertSame(2, app(RecognitionQuota::class)->remainingToday());

        $this->recognise()->assertRedirect(route('log.confirm'));
        $this->recognise()->assertRedirect(route('log.confirm'));
    }

    public function test_the_owner_sees_what_everybody_used_and_nobody_else_does(): void
    {
        $them = User::factory()->create();
        $this->actingAs($them);
        $this->recognise();

        $owner = User::factory()->create();
        $owner->forceFill(['is_owner' => true])->save();

        $this->actingAs($owner);
        $this->recognise();

        // Two recognitions by two people, and the owner's screen shows the
        // total — not one of them, and not a list of who.
        $this->get(route('invites.index'))->assertOk()
            ->assertSee('<span class="usage-total">2</span>', false);

        // Anybody else cannot reach the screen at all, which is where that
        // number lives.
        $this->actingAs($them);
        $this->get(route('invites.index'))->assertForbidden();
    }

    public function test_a_limit_of_zero_stops_recognition_rather_than_removing_the_limit(): void
    {
        // A misread setting must never be the one that turns the limit off.
        config(['nutrition.recognition.daily_limit' => 0]);
        $this->signIn();

        $this->recognise()->assertSessionHasErrors('photo');
        $this->assertSame(0, Recognition::query()->count());
    }
}
