<?php

declare(strict_types=1);

namespace App\Nutrition\Recognisers;

use App\Nutrition\Contracts\FoodRecogniser;
use App\Nutrition\Exceptions\RecognitionFailedException;
use App\Nutrition\NutrientProfile;
use App\Nutrition\NutrientSource;
use App\Nutrition\PreparedPhoto;
use App\Nutrition\RecognisedItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The one real recogniser: Google's Gemini vision model. It is asked to do
 * recognition only — name the dishes, estimate portions, and give a rough macro
 * guess. Those macros are always tagged {@see NutrientSource::Estimated}; they
 * are a last-resort fallback, never presented as fact.
 *
 * The API key travels in the `x-goog-api-key` header, never in the URL. In the
 * test suite this class is never constructed — {@see FakeRecogniser} takes its
 * place — so CI needs no key and makes no call.
 */
class GeminiRecogniser implements FoodRecogniser
{
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

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->timeout(30)
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
        } catch (Throwable) {
            // Deliberately no exception detail: it can carry request context.
            throw new RecognitionFailedException('The recogniser could not be reached.');
        }

        if (! $response->successful()) {
            // The error body carries Google's diagnostic (and never the key,
            // which is in the header) — log it for every failure, so a rejected
            // key, a missing model or a zero free-tier quota is all visible.
            Log::warning('Gemini recognition error', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            // Each status names its own cause. A message that blames the wrong
            // thing (e.g. "try again shortly" for a structural quota of zero) is
            // worse than a generic one — it sends the operator to fix the wrong
            // knob. Note that a 429 here is usually a free-tier limit of 0 on the
            // configured model, not a transient burst, so it points at the model.
            throw new RecognitionFailedException(match ($response->status()) {
                400 => 'The recogniser rejected the request as malformed.',
                401, 403 => 'The recogniser rejected the API key.',
                404 => 'The configured recogniser model was not found; check GEMINI_MODEL.',
                429 => 'The recogniser has no quota for the configured model; check the plan or GEMINI_MODEL.',
                // A 5xx is Google's side, not ours: the model is overloaded
                // ("UNAVAILABLE, high demand"). Transient and worth retrying, so
                // say so rather than implying the request or config is at fault.
                500, 502, 503, 504 => 'The recogniser is temporarily unavailable (the model is busy); please try again shortly.',
                default => 'The recogniser returned an error.',
            });
        }

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
