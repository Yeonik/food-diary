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

## Barcodes

A packaged product can be logged by its barcode instead of its name: scan it, or
type the number under it, and it resolves to a single **Open Food Facts** product
you confirm a weight for. It is a verified source — never an estimate.

Scanning uses the browser's native
[`BarcodeDetector`](https://developer.mozilla.org/en-US/docs/Web/API/BarcodeDetector)
on a still frame from the same system camera the photo path uses — no
`getUserMedia`, no in-page viewfinder, and **no scanner library** (ZXing, Quagga)
pulled in. That API is not everywhere: **Firefox, Safari and iOS do not support
it.** There, the app says so plainly and the manual number field — which is
always present — is the whole feature. A single still can also be blurred or
angled, so the path to "just type the number" is never hidden.

Because it is a real browser API, `BarcodeDetector` is **not exercised in CI**;
verify it by hand as with the other integrations (see below).

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

## Front-end: zero tooling

There is no build step. The interface is server-rendered Blade; the stylesheet
(`public/css/app.css`) and the fonts (`public/fonts/`) are served as static
files. No Node, npm, Vite or Tailwind — not in the Docker image, not in CI. This
is a decision, not an omission: a self-hosted single-user tool is simpler to run
and to verify when the whole front-end is one stylesheet and a handful of inline
SVG icons.

The typeface is **Manrope** (SIL Open Font License; the licence travels with the
files in `public/fonts/OFL.txt`). Three weights ship — 400, 600, 800 — in the
Latin and Cyrillic subsets only; Latin-ext and Vietnamese are dropped to keep
the payload small on mobile data, where the app is used. Cyrillic is shipped
because the interface is bilingual (Russian and English). The font is
self-hosted rather than fetched from a font CDN at runtime, because a
third-party request from a page that logs what someone eats is a leak the user
did not ask for.

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

The diary is then at <http://localhost:8000> (change the host port with
`APP_PORT`). Photo recognition needs a Gemini key; **without one it fails loudly
and never invents a result** — there is no "fake" mode in the running app (the
fake recogniser exists only in the test suite). Manual entry, the library,
weight and goals all work with no keys.

Configure recognition and lookups in a `.env`:

- `GEMINI_API_KEY=...` — from Google AI Studio. Required for photo recognition.
- `USDA_API_KEY=...` — free from <https://api.data.gov>. Sent as a header, never
  in a URL. Without it, USDA matches are skipped with a notice.
- Open Food Facts needs **no key**.
- `APP_ACCESS_PASSWORD=...` — optional; a plaintext value or a bcrypt hash. Set
  it to lock the instance; left blank, the diary is open.

See `.env.example` for the full list.

## Deploying (Railway)

The same `Dockerfile` that runs it locally runs it on [Railway](https://railway.com):
connect the repository, add a volume, set the variables, deploy. It is a
single-user tool served by `php artisan serve` — adequate for one person, not a
high-traffic host.

**Persistent volume (required).** Everything that is written — the SQLite
database, uploaded photos, sessions and cache (both in SQLite), logs — lives
under `/app/storage`. Attach one volume:

- Settings → Volumes → **Add Volume**, mount path **`/app/storage`**.

Without it the database is wiped on every redeploy. The first deploy starts with
an empty volume; the entrypoint recreates the framework directories on each
start, so an empty volume is fine.

**Port.** Railway assigns the port through `$PORT`; the entrypoint already binds
to it. Do **not** set `PORT` yourself.

**Migrations run automatically** on each deploy (`php artisan migrate --force`
in the entrypoint). This is deliberate: a single instance on a volume has no
migration race, the project's migrations are additive and nullable, and a failed
migration fails the deploy cleanly (the container exits, Railway keeps the
previous version) rather than serving a half-migrated schema. Escape hatch for a
risky migration: open a Railway shell, copy
`/app/storage/app/food-diary.sqlite` aside first; if you want to gate the
migration by hand, run it there with `php artisan migrate` before the code that
needs it.

**Variables to set** in the service (Settings → Variables):

| Variable | Value |
| --- | --- |
| `APP_KEY` | generate once locally: `php artisan key:generate --show` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://<your-app>.up.railway.app` |
| `APP_ACCESS_PASSWORD` | a **bcrypt hash** (see the `$` note below) |
| `SESSION_SECURE_COOKIE` | `true` |
| `DB_CONNECTION` | `sqlite` |
| `DB_DATABASE` | `/app/storage/app/food-diary.sqlite` (must match the volume) |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `GEMINI_API_KEY` | your Gemini key (photo recognition) |
| `GEMINI_MODEL` | optional, e.g. `gemini-3.5-flash`; a default applies if unset |
| `USDA_API_KEY` | optional; without it USDA matches are skipped |
| `OFF_USER_AGENT` | optional, e.g. `food-diary/1.0 (self-hosted)` |

`APP_KEY` must be a fixed value you set once — not regenerated per deploy, or
every session breaks on each release.

**The `$` in a bcrypt hash — read this.** A bcrypt hash looks like
`$2y$12$....`. Railway interpolates only its own `${{ ... }}` reference syntax,
so a lone `$` is stored literally and the hash is safe to paste **as-is**.
Do **not** reuse a hash you doubled for `docker-compose`: in a compose `.env`
each `$` is escaped to `$$`, and Railway would store those doubled dollars
verbatim, so `password_verify` fails **silently** — the password just stops
matching with nothing in the logs. On Railway use the raw single-`$` hash
straight from `php -r 'echo password_hash("your-password", PASSWORD_BCRYPT), PHP_EOL;'`.

**Health check.** The app exposes `/up` (returns 200, outside the password
gate). Point Railway's health check path at `/up` if you enable one.

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

**Barcode scanning** is a browser API and cannot run in CI, so verify it by hand:
open `/log/barcode` in a supporting browser (Chrome or Edge on Android or
desktop), photograph a real barcode, and confirm the code is read and resolves.
In Firefox, Safari or on iOS, confirm the app states that scanning is
unavailable and that typing the number still works.
