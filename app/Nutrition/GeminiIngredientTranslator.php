<?php

declare(strict_types=1);

namespace App\Nutrition;

use App\Nutrition\Contracts\IngredientTranslator;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Translates a non-Latin ingredient name to English with Gemini, so USDA — an
 * English-indexed database and the one that actually covers raw ingredients —
 * can be searched for it.
 *
 * Three deliberate bounds on how much of the shared key this spends:
 *
 *   1. **It only fires on a non-Latin term.** "rice" is left alone; "рис" is
 *      translated. An English-typing person never triggers a call.
 *   2. **It is cached by term.** "рис" is translated once and reused, so the
 *      cost is bounded by the number of DISTINCT foreign words ever typed, not
 *      by the number of searches.
 *   3. **It fails open.** A timeout, a bad key, a malformed answer — any of
 *      these returns null, and the caller searches with the original term. The
 *      ingredient search is never blocked on a translation.
 *
 * **It is deliberately NOT metered against the recognition quota.** That quota
 * counts photo recognitions, and its refusal names that cause on purpose;
 * charging a tiny text translation to it would produce a refusal that names the
 * wrong reason, which this project treats as worse than no limit. The three
 * bounds above are this call's protection instead.
 *
 * Like the recogniser, the real client is never constructed in the test suite —
 * a fake takes its place — so CI needs no key and makes no call.
 */
final class GeminiIngredientTranslator implements IngredientTranslator
{
    /** Translations are stable, so they are kept for a good while. */
    private const CACHE_DAYS = 30;

    private const PROMPT = <<<'TXT'
        Translate this food or ingredient name to a short English name suitable
        for searching a nutrition database. Reply with only the English name, no
        quotes, no explanation. If it is already English, reply with it unchanged.

        Name:
        TXT;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly ?string $apiKey,
        private readonly Cache $cache,
        private readonly int $timeoutSeconds = 15,
    ) {}

    public function toEnglish(string $term): ?string
    {
        $trimmed = trim($term);

        // Nothing to translate: already Latin (or empty). This is the common
        // case for an English-typing person and it never touches the network.
        if ($trimmed === '' || ! $this->looksNonLatin($trimmed)) {
            return null;
        }

        if ($this->apiKey === null || $this->apiKey === '') {
            return null;
        }

        $key = 'ingredient_translation:'.md5(mb_strtolower($trimmed));

        /** @var string|null $cached */
        $cached = $this->cache->get($key);
        if (is_string($cached)) {
            return $cached === '' ? null : $cached;
        }

        $english = $this->translate($trimmed);

        // Cache the outcome either way — a miss too, as an empty string, so a
        // word that cannot be translated is not retried on every keystroke's
        // worth of searching. Both stored the same length of time.
        $this->cache->put($key, $english ?? '', now()->addDays(self::CACHE_DAYS));

        return $english;
    }

    /**
     * The actual call. Any failure returns null — fail open.
     */
    private function translate(string $term): ?string
    {
        try {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->connectTimeout(min(10, $this->timeoutSeconds))
                ->timeout($this->timeoutSeconds)
                ->post(rtrim($this->baseUrl, '/')."/models/{$this->model}:generateContent", [
                    'contents' => [[
                        'parts' => [['text' => self::PROMPT.' '.$term]],
                    ]],
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (! is_string($text)) {
            return null;
        }

        $english = trim(str_replace(['"', "\n"], ['', ' '], $text));

        // A sane single term, not a paragraph the model decided to add. Guard
        // against a runaway answer becoming the search query.
        if ($english === '' || mb_strlen($english) > 100) {
            return null;
        }

        return $english;
    }

    /**
     * True when the term carries characters outside the Latin range — the cheap
     * signal that USDA's English index will not find it as typed. Cyrillic is
     * the case this instance exists for; the test is broader so it does not have
     * to enumerate scripts.
     */
    private function looksNonLatin(string $term): bool
    {
        return preg_match('/[^\p{Latin}\p{Common}]/u', $term) === 1;
    }
}
