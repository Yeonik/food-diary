<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Nutrition\Exceptions\RecognitionFailedException;
use App\Nutrition\PreparedPhoto;
use App\Nutrition\Recognisers\GeminiRecogniser;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The recogniser must name the cause of a failure honestly. A message that
 * blames a transient rate limit for a structural quota-of-zero sends the
 * operator to fix the wrong knob, so each provider status maps to its own text.
 */
class GeminiRecogniserTest extends TestCase
{
    private string $photoPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->photoPath = tempnam(sys_get_temp_dir(), 'gem').'.jpg';
        file_put_contents($this->photoPath, 'not-really-a-jpeg-but-bytes-are-enough');
    }

    protected function tearDown(): void
    {
        @unlink($this->photoPath);

        parent::tearDown();
    }

    private function recogniser(?string $key = 'a-valid-looking-key'): GeminiRecogniser
    {
        return new GeminiRecogniser(
            'https://generativelanguage.googleapis.com/v1beta',
            'gemini-3.5-flash',
            $key,
        );
    }

    private function photo(): PreparedPhoto
    {
        return new PreparedPhoto($this->photoPath, 'image/jpeg', 400, 300);
    }

    public function test_a_missing_key_fails_before_any_call_is_made(): void
    {
        Http::fake();

        $this->expectException(RecognitionFailedException::class);
        $this->expectExceptionMessage('not configured');

        try {
            $this->recogniser(key: null)->recognise($this->photo());
        } finally {
            Http::assertNothingSent();
        }
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function errorStatuses(): array
    {
        return [
            'malformed request (400)' => [400, 'malformed'],
            'rejected key (401)' => [401, 'API key'],
            'forbidden (403)' => [403, 'API key'],
            'unknown model (404)' => [404, 'model was not found'],
            'zero quota (429)' => [429, 'no quota for the configured model'],
            'server error (500)' => [500, 'returned an error'],
        ];
    }

    #[DataProvider('errorStatuses')]
    public function test_each_provider_status_names_its_own_cause(int $status, string $expected): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                ['error' => ['code' => $status, 'message' => 'provider diagnostic']],
                $status,
            ),
        ]);

        $this->expectException(RecognitionFailedException::class);
        $this->expectExceptionMessage($expected);

        $this->recogniser()->recognise($this->photo());
    }

    public function test_it_parses_a_native_name_when_the_model_gives_one(): void
    {
        $modelText = json_encode([
            ['name' => 'Pobeda chocolate', 'native_name' => 'Победа', 'grams' => 100, 'confidence' => 0.9,
                'kcal_per_100g' => 460, 'protein_g_per_100g' => 9, 'fat_g_per_100g' => 29, 'carbs_g_per_100g' => 42],
            ['name' => 'Green tea', 'grams' => 250, 'confidence' => 0.8,
                'kcal_per_100g' => 1, 'protein_g_per_100g' => 0, 'fat_g_per_100g' => 0, 'carbs_g_per_100g' => 0],
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => $modelText]]]]],
            ]),
        ]);

        $items = $this->recogniser()->recognise($this->photo());

        $this->assertCount(2, $items);
        // The first dish carries both names; the second, with no native_name, is null.
        $this->assertSame('Pobeda chocolate', $items[0]->name);
        $this->assertSame('Победа', $items[0]->nativeName);
        $this->assertNull($items[1]->nativeName);
    }

    public function test_a_429_is_not_reported_as_a_transient_rate_limit(): void
    {
        // A free-tier quota of 0 answers 429, but "try again shortly" would be a
        // lie: retrying never helps. The message must point at the model instead.
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                ['error' => ['message' => 'limit: 0']],
                429,
            ),
        ]);

        try {
            $this->recogniser()->recognise($this->photo());
            $this->fail('Expected a RecognitionFailedException.');
        } catch (RecognitionFailedException $e) {
            $this->assertStringNotContainsStringIgnoringCase('try again', $e->getMessage());
            $this->assertStringContainsString('GEMINI_MODEL', $e->getMessage());
        }
    }
}
