<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Somebody is told that photographs leave this machine before they have an
 * account, not once they are standing in front of an upload field.
 *
 * The claim the README makes is that a meal photo goes to a third party with its
 * location stripped. Saying so only on the screen that does the uploading tells
 * people after they have joined and started keeping a diary here — which is late
 * enough to be no choice at all.
 */
class RecognitionNoticeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function bothLocales(): array
    {
        return ['English' => ['en'], 'Russian' => ['ru']];
    }

    #[DataProvider('bothLocales')]
    public function test_the_registration_screen_says_photographs_are_sent_to_be_recognised(string $locale): void
    {
        $this->withCookie(SetLocale::COOKIE, $locale)->get(route('register'))
            ->assertOk()
            ->assertSee(__('auth.register_privacy', [], $locale));
    }

    #[DataProvider('bothLocales')]
    public function test_the_notice_names_the_recogniser_and_the_stripping(string $locale): void
    {
        // Not a general reassurance about privacy: the company the photo goes
        // to, and the metadata that does not go with it.
        $notice = __('auth.register_privacy', [], $locale);

        $this->assertStringContainsString('Gemini', $notice);
        $this->assertStringContainsString('EXIF', $notice);
        $this->assertStringContainsString('GPS', $notice);
    }

    #[DataProvider('bothLocales')]
    public function test_the_upload_screen_still_says_it_too(string $locale): void
    {
        // The one place it was said before. Both stay: the notice at
        // registration is consent, this one is a reminder at the moment it
        // happens.
        $this->signIn();

        $this->withCookie(SetLocale::COOKIE, $locale)->get(route('log.photo'))
            ->assertOk()
            ->assertSee(__('photo.privacy', [], $locale));
    }
}
