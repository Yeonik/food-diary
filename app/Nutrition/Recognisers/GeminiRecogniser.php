<?php

declare(strict_types=1);

namespace App\Nutrition\Recognisers;

use App\Nutrition\Contracts\FoodRecogniser;
use App\Nutrition\Exceptions\RecognitionFailedException;
use App\Nutrition\NutrientProfile;
use App\Nutrition\NutrientSource;
use App\Nutrition\PreparedPhoto;
use App\Nutrition\RecognisedItem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * The one real recogniser: Google's Gemini vision model. It is asked to do
 * recognition only — name the dishes, estimate portions, and give a rough macro
 * guess. Those macros are always tagged {@see NutrientSource::Estimated}; they
 * are a last-resort fallback, never presented as fact.
 *
 * The API key travels in the `x-goog-api-key` header, never in the URL. In the
 * test suite this class is never constructed — {@see FakeRecogniser} takes its
 * place — so CI needs no key and makes no call.
 *
 * The model is often overloaded (503) or slow to answer a photo. Those are
 * transient, so a small number of attempts with a noticeable pause are made
 * before giving up — and the final message says how many times it tried, so a
 * retry never silently hides the failure. Structural errors (a bad key, an
 * unknown model, a spent quota) are never retried: retrying cannot help and, for
 * a 429, would only burn more of a scarce quota.
 */
class GeminiRecogniser implements FoodRecogniser
{
    /** Total tries, initial included. Two: if the model is silent twice, it is busy in earnest. */
    private const MAX_ATTEMPTS = 2;

    /** Pause between tries. Noticeable, and well within the free tier's 10 requests a minute. */
    private const RETRY_PAUSE_SECONDS = 8;

    /** A dead network or DNS should fail fast rather than wait out the read timeout. */
    private const CONNECT_TIMEOUT_SECONDS = 10;

    /** Server-side statuses worth retrying — the model is busy, not our request wrong. */
    private const RETRYABLE_STATUSES = [500, 502, 503, 504];

    private const PROMPT = <<<'TXT'
        You are a food recognition step, not a nutrition database. Identify each
        distinct dish visible in the photo and estimate its portion in grams.
        Give a rough macro guess per 100 g as a fallback only.

        Give two names. "name" is an English name, used to search an
        English-language database. "native_name" is the name exactly as printed
        on the packaging in its original language (for example Russian) — set it
        to null if the item is already English or has no distinct packaged name.

        Reply with a JSON array. Each element:
        {"name": string, "native_name": string or null, "grams": number,
         "confidence": number 0..1, "kcal_per_100g": number,
         "protein_g_per_100g": number, "fat_g_per_100g": number,
         "carbs_g_per_100g": number}
        Return only the JSON array.
        TXT;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly ?string $apiKey,
        private readonly int $timeoutSeconds = 60,
    ) {}

    /**
     * @return list<RecognisedItem>
     */
    public function recognise(PreparedPhoto $photo): array
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw new RecognitionFailedException('The recogniser is not configured.');
        }

        // Observability without leaking the key: the key travels only in the
        // header below and is never logged.
        Log::info('Gemini recognition request', [
            'model' => $this->model,
            'image_bytes' => strlen($photo->contents()),
        ]);

        $response = $this->send($photo);

        $text = $response->json('candidates.0.content.parts.0.text');

        // The raw model answer — dishes and portions — for this photo. This is
        // the actual recogniser output, distinct from anything the app derives.
        Log::info('Gemini recognition response', [
            'model' => $this->model,
            'text' => is_string($text) ? $text : null,
        ]);

        return $this->parse($text);
    }

    /**
     * Post the photo, retrying a busy model or a timed-out connection a bounded
     * number of times, and return the successful response. A structural error is
     * raised at once; a transient one only after every attempt is spent.
     */
    private function send(PreparedPhoto $photo): Response
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                    ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                    ->timeout($this->timeoutSeconds)
                    ->post(rtrim($this->baseUrl, '/')."/models/{$this->model}:generateContent", [
                        'contents' => [[
                            'parts' => [
                                ['text' => self::PROMPT],
                                ['inline_data' => [
                                    'mime_type' => $photo->mimeType,
                                    'data' => base64_encode($photo->contents()),
                                ]],
                            ],
                        ]],
                        'generationConfig' => ['response_mime_type' => 'application/json'],
                    ]);
            } catch (ConnectionException $e) {
                // A timeout or connection error. The message names the transport
                // fault (e.g. "Operation timed out"); it never carries the key,
                // which lives in a header, not the URL.
                Log::warning('Gemini recognition attempt failed', [
                    'attempt' => $attempt,
                    'reason' => 'connection',
                    'exception' => $e::class,
                    'message' => mb_substr($e->getMessage(), 0, 200),
                ]);

                if ($attempt < self::MAX_ATTEMPTS) {
                    Sleep::for(self::RETRY_PAUSE_SECONDS)->seconds();

                    continue;
                }

                throw new RecognitionFailedException(sprintf(
                    'The recogniser could not be reached after %d attempts; please try again shortly.',
                    self::MAX_ATTEMPTS,
                ));
            }

            if (in_array($response->status(), self::RETRYABLE_STATUSES, true)) {
                // The model is overloaded (503 "UNAVAILABLE, high demand") or the
                // server erred — transient, so try again before giving up.
                Log::warning('Gemini recognition attempt failed', [
                    'attempt' => $attempt,
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 300),
                ]);

                if ($attempt < self::MAX_ATTEMPTS) {
                    Sleep::for(self::RETRY_PAUSE_SECONDS)->seconds();

                    continue;
                }

                throw new RecognitionFailedException(sprintf(
                    'The recogniser is unavailable after %d attempts (the model is busy); please try again shortly.',
                    self::MAX_ATTEMPTS,
                ));
            }

            if (! $response->successful()) {
                return $this->failStructurally($response);
            }

            return $response;
        }

        // Unreachable: the loop either returns or throws on the last attempt.
        throw new RecognitionFailedException('The recogniser could not be reached.');
    }

    /**
     * A non-retryable failure: name its own cause and stop. Retrying any of these
     * cannot help, and a message that blames the wrong thing is worse than none.
     */
    private function failStructurally(Response $response): never
    {
        // The error body carries Google's diagnostic (never the key, which is in
        // the header) — log it so a rejected key or a missing model is visible.
        Log::warning('Gemini recognition error', [
            'status' => $response->status(),
            'body' => mb_substr($response->body(), 0, 500),
        ]);

        throw new RecognitionFailedException(match ($response->status()) {
            400 => 'The recogniser rejected the request as malformed.',
            401, 403 => 'The recogniser rejected the API key.',
            404 => 'The configured recogniser model was not found; check GEMINI_MODEL.',
            429 => 'The recogniser has no quota for the configured model; check the plan or GEMINI_MODEL.',
            default => 'The recogniser returned an error.',
        });
    }

    /**
     * @return list<RecognisedItem>
     */
    private function parse(mixed $text): array
    {
        if (! is_string($text)) {
            throw new RecognitionFailedException('The recogniser returned no readable result.');
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded)) {
            throw new RecognitionFailedException('The recogniser returned an unexpected result.');
        }

        $items = [];

        foreach ($decoded as $row) {
            if (! is_array($row) || ! is_string($row['name'] ?? null)) {
                continue;
            }

            $items[] = new RecognisedItem(
                name: $row['name'],
                estimatedGrams: $this->number($row['grams'] ?? null),
                confidence: $this->number($row['confidence'] ?? null),
                nativeName: $this->text($row['native_name'] ?? null),
                estimatedProfile: new NutrientProfile(
                    kcal: $this->number($row['kcal_per_100g'] ?? null),
                    proteinG: $this->number($row['protein_g_per_100g'] ?? null),
                    fatG: $this->number($row['fat_g_per_100g'] ?? null),
                    carbsG: $this->number($row['carbs_g_per_100g'] ?? null),
                    source: NutrientSource::Estimated,
                ),
            );
        }

        return $items;
    }

    private function number(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
