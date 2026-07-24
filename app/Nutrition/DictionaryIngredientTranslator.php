<?php

declare(strict_types=1);

namespace App\Nutrition;

use App\Nutrition\Contracts\IngredientTranslator;

/**
 * A curated Russian→English ingredient dictionary, consulted before Gemini.
 *
 * The common ingredients a recipe is built from are a small, stable set, and
 * translating them does not need a language model: "картофель" is always
 * "potato". Looking them up in a table is instant, deterministic, costs no key,
 * and — the reason this exists — is unaffected by the free tier answering 429 or
 * 503. Only the long tail of words the table does not know falls through to the
 * Gemini {@see $fallback}, which is where the rate limit and the retry logic
 * actually matter.
 *
 * It also handles a multi-word phrase without the model (the pivot, #3): a
 * phrase whose only known word is the ingredient — "сырой картофель",
 * "варёный рис" — resolves to that word's English, since the modifier is not a
 * food and the re-rank surfaces the raw form anyway. A phrase naming two known
 * foods is left to the fallback rather than guessed at.
 */
final class DictionaryIngredientTranslator implements IngredientTranslator
{
    /**
     * Curated Russian → English, for searching USDA. Keys are lower-cased with
     * ё folded to е (the query is folded the same way), so both spellings hit.
     * The English is the plain food noun; the re-rank lifts the raw base form.
     *
     * Nouns only — modifiers like "сырой" (raw) are deliberately absent, so the
     * pivot ignores them. A few common compounds are kept as whole phrases where
     * the head noun alone would be too vague ("грудка" → which breast?).
     *
     * @var array<string, string>
     */
    private const MAP = [
        // Vegetables
        'картофель' => 'potato', 'картошка' => 'potato', 'морковь' => 'carrot',
        'лук' => 'onion', 'чеснок' => 'garlic', 'помидор' => 'tomato', 'томат' => 'tomato',
        'огурец' => 'cucumber', 'капуста' => 'cabbage', 'цветная капуста' => 'cauliflower',
        'брокколи' => 'broccoli', 'свекла' => 'beets', 'перец' => 'pepper',
        'кабачок' => 'zucchini', 'баклажан' => 'eggplant', 'тыква' => 'pumpkin',
        'шпинат' => 'spinach', 'салат' => 'lettuce', 'редис' => 'radishes',
        'горох' => 'peas', 'фасоль' => 'beans', 'кукуруза' => 'corn',
        'грибы' => 'mushrooms', 'шампиньоны' => 'mushrooms', 'сельдерей' => 'celery',

        // Fruits
        'яблоко' => 'apple', 'банан' => 'banana', 'груша' => 'pear', 'апельсин' => 'orange',
        'мандарин' => 'tangerine', 'лимон' => 'lemon', 'виноград' => 'grapes',
        'клубника' => 'strawberries', 'малина' => 'raspberries', 'черника' => 'blueberries',
        'вишня' => 'cherries', 'слива' => 'plums', 'персик' => 'peach', 'абрикос' => 'apricots',
        'ананас' => 'pineapple', 'киви' => 'kiwifruit', 'арбуз' => 'watermelon',
        'дыня' => 'melon', 'авокадо' => 'avocado',

        // Grains and carbohydrates
        'рис' => 'rice', 'гречка' => 'buckwheat', 'овсянка' => 'oats', 'овес' => 'oats',
        'пшено' => 'millet', 'перловка' => 'barley', 'макароны' => 'pasta',
        'спагетти' => 'spaghetti', 'хлеб' => 'bread', 'мука' => 'flour',
        'булгур' => 'bulgur', 'киноа' => 'quinoa', 'кускус' => 'couscous',

        // Protein
        'курица' => 'chicken', 'куриная грудка' => 'chicken breast', 'куриное филе' => 'chicken breast',
        'говядина' => 'beef', 'говяжий фарш' => 'ground beef', 'свинина' => 'pork',
        'индейка' => 'turkey', 'баранина' => 'lamb', 'яйцо' => 'egg', 'яйца' => 'egg',
        'рыба' => 'fish', 'лосось' => 'salmon', 'семга' => 'salmon', 'тунец' => 'tuna',
        'треска' => 'cod', 'скумбрия' => 'mackerel', 'креветки' => 'shrimp', 'тофу' => 'tofu',

        // Dairy
        'молоко' => 'milk', 'творог' => 'cottage cheese', 'сыр' => 'cheese',
        'сметана' => 'sour cream', 'сливки' => 'cream', 'кефир' => 'kefir', 'йогурт' => 'yogurt',
        'сливочное масло' => 'butter', 'масло сливочное' => 'butter',

        // Fats, sugars, nuts
        'растительное масло' => 'vegetable oil', 'оливковое масло' => 'olive oil',
        'подсолнечное масло' => 'sunflower oil', 'сахар' => 'sugar', 'соль' => 'salt',
        'мед' => 'honey', 'орехи' => 'nuts', 'грецкий орех' => 'walnuts', 'миндаль' => 'almonds',
        'фундук' => 'hazelnuts', 'арахис' => 'peanuts', 'кешью' => 'cashews',
    ];

    public function __construct(
        private readonly NameMatcher $matcher,
        private readonly ?IngredientTranslator $fallback = null,
    ) {}

    public function toEnglish(string $term): ?string
    {
        $normalized = $this->normalise($term);
        if ($normalized === '') {
            return null;
        }

        // The whole phrase is a known ingredient (including a curated compound).
        if (isset(self::MAP[$normalized])) {
            return self::MAP[$normalized];
        }

        // The pivot: a phrase whose one known word is the ingredient. "сырой
        // картофель" keeps only "картофель" → potato; the modifier is no food and
        // is dropped. Two known foods is ambiguous — hand it to the fallback
        // rather than pick one.
        $known = [];
        foreach ($this->matcher->significantTokens($normalized) as $token) {
            if (isset(self::MAP[$token])) {
                $known[$token] = self::MAP[$token];
            }
        }
        if (count($known) === 1) {
            return array_values($known)[0];
        }

        // Not in the table: the long tail, where a language model earns its keep.
        return $this->fallback?->toEnglish($term);
    }

    /**
     * Lower-case, trim, and fold ё to е so "свёкла" and "свекла", "мёд" and
     * "мед" reach the same key. The dictionary is stored folded to match.
     */
    private function normalise(string $term): string
    {
        return str_replace('ё', 'е', mb_strtolower(trim($term)));
    }
}
