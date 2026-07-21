<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Nutrition\Exceptions\RecognitionFailedException;
use App\Nutrition\PreparedPhoto;
use App\Nutrition\Recognisers\GeminiRecogniser;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The recogniser must name the cause of a failure honestly, and a busy model —
 * the common case — must be retried a bounded number of times rather than failing
 * on the first 503 or slow answer. A retry never hides the failure: when every
 * attempt is spent the message says so and how many times it tried.
 */
class GeminiRecogniserTest extends TestCase
{
    private string $photoPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Retries pause for real seconds in production; fake the clock so the
        // suite neither waits nor makes a real call.
        Sleep::fake();

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

    /**
     * A stubbed successful model reply carrying the given dishes as its JSON text.
     *
     * @param  list<array<string, mixed>>  $dishes
     */
    private function modelReply(array $dishes): PromiseInterface
    {
        return Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode($dishes)]]]]]]);
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
        // Structural failures only — none of these is worth retrying. The
        // transient 5xx statuses are covered by the retry tests below.
        return [
            'malformed request (400)' => [400, 'malformed'],
            'rejected key (401)' => [401, 'API key'],
            'forbidden (403)' => [403, 'API key'],
            'unknown model (404)' => [404, 'model was not found'],
            'zero quota (429)' => [429, 'no quota for the configured model'],
            'unmapped status (418)' => [418, 'returned an error'],
        ];
    }

    #[DataProvider('errorStatuses')]
    public function test_each_structural_status_names_its_own_cause_and_is_not_retried(int $status, string $expected): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                ['error' => ['code' => $status, 'message' => 'provider diagnostic']],
                $status,
            ),
        ]);

        try {
            $this->recogniser()->recognise($this->photo());
            $this->fail('Expected a RecognitionFailedException.');
        } catch (RecognitionFailedException $e) {
            $this->assertStringContainsString($expected, $e->getMessage());
        }

        // A structural error is final: it is tried once and not retried.
        Http::assertSentCount(1);
        Sleep::assertSleptTimes(0);
    }

    public function test_a_429_is_not_reported_as_a_transient_rate_limit(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'limit: 0']], 429),
        ]);

        try {
            $this->recogniser()->recognise($this->photo());
            $this->fail('Expected a RecognitionFailedException.');
        } catch (RecognitionFailedException $e) {
            $this->assertStringNotContainsStringIgnoringCase('try again', $e->getMessage());
            $this->assertStringContainsString('GEMINI_MODEL', $e->getMessage());
        }
    }

    public function test_it_parses_a_native_name_when_the_model_gives_one(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => $this->modelReply([
                ['name' => 'Pobeda chocolate', 'native_name' => 'Победа', 'grams' => 100, 'confidence' => 0.9,
                    'kcal_per_100g' => 460, 'protein_g_per_100g' => 9, 'fat_g_per_100g' => 29, 'carbs_g_per_100g' => 42],
                ['name' => 'Green tea', 'grams' => 250, 'confidence' => 0.8,
                    'kcal_per_100g' => 1, 'protein_g_per_100g' => 0, 'fat_g_per_100g' => 0, 'carbs_g_per_100g' => 0],
            ]),
        ]);

        $items = $this->recogniser()->recognise($this->photo());

        $this->assertCount(2, $items);
        $this->assertSame('Pobeda chocolate', $items[0]->name);
        $this->assertSame('Победа', $items[0]->nativeName);
        $this->assertNull($items[1]->nativeName);
    }

    public function test_a_busy_model_is_retried_and_then_succeeds(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['error' => ['message' => 'high demand']], 503)
                ->push(['candidates' => [['content' => ['parts' => [['text' => json_encode([
                    ['name' => 'Rice', 'grams' => 150, 'confidence' => 0.8,
                        'kcal_per_100g' => 130, 'protein_g_per_100g' => 2.7, 'fat_g_per_100g' => 0.3, 'carbs_g_per_100g' => 28],
                ])]]]]]], 200),
        ]);

        $items = $this->recogniser()->recognise($this->photo());

        $this->assertCount(1, $items);
        Http::assertSentCount(2);
        Sleep::assertSleptTimes(1);
    }

    public function test_it_fails_honestly_after_exhausting_retries_on_a_busy_model(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'high demand']], 503),
        ]);

        try {
            $this->recogniser()->recognise($this->photo());
            $this->fail('Expected a RecognitionFailedException.');
        } catch (RecognitionFailedException $e) {
            $this->assertStringContainsString('after 2 attempts', $e->getMessage());
            $this->assertStringContainsStringIgnoringCase('unavailable', $e->getMessage());
        }

        // It tried twice, with one pause between — never silently once.
        Http::assertSentCount(2);
        Sleep::assertSleptTimes(1);
    }

    public function test_a_timed_out_connection_is_retried_and_reports_the_attempts(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => function (): void {
                throw new ConnectionException('cURL error 28: Operation timed out after 60000 milliseconds');
            },
        ]);

        try {
            $this->recogniser()->recognise($this->photo());
            $this->fail('Expected a RecognitionFailedException.');
        } catch (RecognitionFailedException $e) {
            $this->assertStringContainsString('after 2 attempts', $e->getMessage());
            $this->assertStringContainsStringIgnoringCase('could not be reached', $e->getMessage());
        }

        Sleep::assertSleptTimes(1);
    }
}
