# food-diary

A self-hosted food diary. Photograph a meal, a vision model names the dishes and
estimates portions, you confirm, and the numbers come from nutrition databases or
your own corrected entries — **never invented by the model.**

## Why it exists

Calorie estimates from a photo are unreliable in a specific way: a model will
confidently produce a number it has no way of knowing, because portion mass
cannot be recovered from a 2D image. Most apps in this space hide that.

This one splits the work by what each part is actually good at. The model does
recognition — naming dishes, guessing rough portions — which it does well. The
numbers come from a nutrition database, from a recipe you defined, or from
entries you corrected earlier. Where none of those has an answer, the entry is
marked as an unverified estimate and looks different in the interface. **The
model never supplies a nutrient value that is presented as fact** — this is
enforced in the resolver and in the log service, not just promised here.

## An honest privacy note

Unlike a local tool, this app **sends your meal photographs to Google's Gemini
API**. That is unavoidable for the recognition step and is stated plainly here
and before the first upload. Two mitigations are implemented, not just promised:

- **All photo metadata is stripped before the image leaves the machine.** The
  photo is re-encoded through GD, which drops the entire EXIF block — GPS
  coordinates, device make and model, and capture time — and the encoder's own
  JPEG comment is removed too. This is verified by a test against a real
  GPS-tagged fixture (`tests/Unit/PhotoPreparerTest.php`).
- **The photo is deleted once the entry is confirmed** (configurable).

Uploads are also treated as attacker-controlled: validated by content rather
than extension, and stored under a generated name outside the web root — the
client filename never becomes part of a path.

## How a dish is resolved

Every recognised dish walks a ladder, and the answering source is recorded and
shown next to the entry:

| Tier | Source | Notes |
|------|--------|-------|
| 1 | **Personal library** | Foods you confirmed, corrected, or defined as recipes. Always first — you verified them. |
| 2 | **USDA FoodData Central** and **Open Food Facts** | Queried in parallel, **not** ranked against each other. USDA is strong at raw foods; Open Food Facts at branded packaged goods. Both matches are shown, each labelled; nothing is auto-selected. |
| 3 | **Estimate** | The model's own macro guess. Offered only when no real source answered, flagged `estimated`, visually distinct, and never counted as verified. |

Confirming a real match writes it to the personal library, so the lower tiers
get used less over time. The system learns your diet with a table, not a model.

## Recipes

Borscht, plov and manty are in no database — every household cooks them
differently, so no reference value can be right for your version. A library item
is therefore one of two things:

- a **direct** nutrient profile (per 100 g), or
- a **recipe** — a list of other library items with weights, from which the
  profile is computed. Ingredients are exactly what the databases *do* cover.

Recipes may reference other recipes; cycles are rejected rather than followed.

A logged entry stores a **snapshot** of the numbers as they were at the moment of
logging. Correcting a library item or editing a recipe later changes future
entries only — last month's totals never silently move. Both directions are
covered by `tests/Feature/SnapshotImmutabilityTest.php`.

## Deliberate interface choices

These are decisions, not missing features:

- Remaining calories is **a number, not a verdict**. No red/green, no warning
  colours, no "over budget" language. A negative remaining is just a number.
- **No streaks, no badges, no daily pass/fail.** A missed day is a missed day.
- Editing and deleting entries is easy and unpenalised.
- Weight is a log and a line — no BMI verdict, no target-weight nagging, no
  commentary on the trend.
- The app never suggests lowering a target. Goals are entirely optional; with no
  goal set, no "remaining" is shown at all.

## No scraping

Only official APIs with an explicit licence are used: USDA (public domain) and
Open Food Facts (ODbL). Sites like health-diet.ru or pbprog.ru publish tables
whose licensing is unclear and whose terms generally forbid it, so they are not
touched. This is a deliberate choice.

## Architecture

PHP 8.3+, Laravel 13, SQLite, Blade with minimal JS, Docker Compose. The domain
lives under `app/Nutrition/`; controllers stay thin.

```
app/Nutrition/
  Contracts/FoodRecogniser.php        recognise(PreparedPhoto): RecognisedItem[]
  Recognisers/GeminiRecogniser.php    the only real recogniser
  Recognisers/FakeRecogniser.php      what CI runs against
  Contracts/NutritionSource.php       search(string): NutrientMatch[]
  Sources/PersonalLibrarySource.php   tier 1
  Sources/UsdaSource.php              tier 2
  Sources/OpenFoodFactsSource.php     tier 2
  FoodResolver.php                    walks the ladder, USDA + OFF in parallel
  RecipeCalculator.php                composes a profile from ingredients
  PhotoPreparer.php                   EXIF strip, resize, content validation
app/Models/  FoodItem, RecipeIngredient, MealEntry, WeightEntry, Goal
```

## Running it

```bash
docker compose up --build
```

The diary is then at <http://localhost:8000>. With the defaults it runs against
the **fake** recogniser, so you can click through the whole flow with no keys.

To use real recognition, set these (in a `.env`, or as compose environment):

- `FOOD_RECOGNISER=gemini`
- `GEMINI_API_KEY=...` — from Google AI Studio.
- `USDA_API_KEY=...` — free from <https://api.data.gov>. Sent as a header, never
  in a URL.
- Open Food Facts needs **no key**.
- `APP_ACCESS_PASSWORD=...` — optional; set it to lock the instance behind one
  password. Left blank, the diary is open (a machine only you can reach).

See `.env.example` for the full list.

## Development

```bash
composer install
vendor/bin/pint --test        # formatting
vendor/bin/phpstan analyse    # static analysis, level 6, no baseline
php artisan test              # the suite
composer audit                # dependency advisories
```

**CI makes no network call and needs no API key.** The Gemini, USDA and Open
Food Facts clients are all faked in the suite, every outbound request is
intercepted, and a stray request fails the test loudly. A portfolio repository
whose CI needs somebody's key is a repository nobody can verify.

The real integrations are exercised by a documented manual step:

```bash
php artisan nutrition:recognise path/to/meal.jpg
```
