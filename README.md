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
  JPEG comment is removed too.

  Orientation is the one tag that cannot simply vanish. A phone does not rotate
  the pixels it captures; it writes an orientation tag and lets the viewer do
  the turning. Dropping that tag with the rest would leave a portrait meal lying
  on its side — for the model, and in the diary. So the rotation is applied to
  the pixels first, and the tag then goes with everything else.

  Both halves are verified against a generated phone-style fixture that carries
  GPS coordinates and an orientation tag: the test asserts no EXIF survives, and
  that the image came out turned the right way
  (`tests/Unit/PhotoPreparerTest.php`).
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

### Open Food Facts thumbnails

On the confirm screen, an Open Food Facts candidate shows the product thumbnail —
pulled **by link** from Open Food Facts, never copied to this server, and hidden
if the link is absent or fails. It appears **only** there: a request to Open Food
Facts for that exact product is already in flight at that moment, so the picture
leaks nothing new. It is **deliberately absent from the library** — a library
view rendering thumbnails would hotlink `openfoodfacts.org` on every visit, a
repeated third-party request from a page that knows what you eat. That is the
same leak the self-hosted font avoids, and the reason the library stays
image-free.

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
- Editing and deleting entries is easy and unpenalised. Delete sits beside Save
  in the entry editor, with no warning beyond the confirm and no tally kept.
- Weight is a log and a line — no BMI verdict, no target-weight nagging, no
  commentary on the trend.
- The app never suggests lowering a target. Goals are entirely optional; with no
  goal set, no "remaining" is shown at all, and the target card is visibly
  dimmed to say the diary works without one.
- **The charts judge nothing either.** Every calorie bar is the same colour
  whatever its value — a day over the goal does not redden — and the goal itself
  is a dashed reference line, not a pass mark. The day's ring never changes
  colour, at any value. A day with no entries is drawn as a zero rather than
  skipped, so the time axis stays honest, and the average divides by the days
  that have entries rather than flattering itself with the whole range.
  `tests/Feature/NeutralChartsTest.php` holds this to the markup.

## Accounts

The diary holds other people's food logs, so authentication is
**[Laravel Fortify](https://laravel.com/docs/fortify)** — not hand-written. There
is no public page: every route in `routes/web.php` sits inside one `auth` group,
so a screen added later is protected because of where it lives rather than
because somebody remembered. `/up`, the health check, is the single exception.

What is deliberately **off**, and why:

- **Password reset by email.** This instance has no deliverable mail configured,
  and a reset screen that promises a link which lands in a log file is worse than
  no screen at all. Until mail exists, **a forgotten password is reset by the
  owner** with an artisan command. The route does not exist, so there is nothing
  to click and be disappointed by.
- **Two-factor and passkeys.** Not offered. `laravel/passkeys` is installed
  anyway — Fortify 1.37 requires it — so it is dormant by decision rather than by
  accident: with the features off Fortify registers none of their routes, and
  because Fortify's migrations are never published, neither the passkey table nor
  the two-factor columns exist. `tests/Feature/AuthenticationTest.php` asserts
  both, so "off" is a fact about the routing table and the schema, not a claim.

Sign-in attempts are throttled to five a minute per email-and-IP pair, and a
wrong password and an unknown address are answered identically — whether an
address has an account here is not something the screen tells a stranger.

## No scraping

Only official APIs with an explicit licence are used: USDA (public domain) and
Open Food Facts (ODbL). Sites like health-diet.ru or pbprog.ru publish tables
whose licensing is unclear and whose terms generally forbid it, so they are not
touched. This is a deliberate choice.

## Front-end: zero tooling

There is no build step. The interface is server-rendered Blade; the stylesheet
(`public/css/app.css`) and the fonts (`public/fonts/`) are served as static
files. No Node, npm, Vite or Tailwind — not in the Docker image, not in CI. This
is a decision, not an omission: a self-hosted diary is simpler to run and to
verify when the whole front-end is one stylesheet and a handful of inline SVG
icons.

### The design system

The layout comes from an approved static design build — plain HTML and CSS —
transcribed rather than eyeballed. Its colour tokens are kept under role names
(`--card` is a card, `--surface` is the screen behind it) so a rule reads as
what it does, and a small set of Blade components carries the recurring pieces:
the source badge, the macro row, the calorie ring, the segmented switch, the
toggle, the stepper, the empty state.

The interface is bilingual — Russian and English — switched from the goal screen
and remembered in a cookie; both locales are held to the same key set by
`tests/Feature/LocalizationTest.php`.

The shell is a rail beside a scrolling column on a wide screen, and a bottom tab
bar with a floating add button below 900px. That frame is sized in `dvh` rather
than `vh` on purpose: a phone's address bar shrinks the visual viewport, and a
`vh`-tall frame pushes the tab bar and the button underneath the browser's own
chrome, where they cannot be tapped.

Photographing a meal uses `capture="environment"` on an ordinary file input, so
the phone opens its own camera app. Meals are shot close up in poor kitchen
light, which is exactly where autofocus, exposure and full sensor resolution
earn their keep — and an in-page viewfinder would give up all three. The barcode
path
does the same thing: its dark panel is a large tap target that opens that
camera, not a live preview — nothing is being scanned until a frame comes back.

### Progressive enhancement, and its one exception

JavaScript in this app is one file of under 200 lines, and almost everything
works without it. Every form is a plain POST. The toggles are checkboxes that
submit with their form. The add menu and the reveal of an entry's edit and
delete icons are `<details>` elements. The stepper's ± buttons only nudge a
number input that is editable anyway. The "log by hand" dialog is a native
`<dialog>`, and with scripting off its openers are ordinary links to the same
page. Language, dates, periods and paging are links and submit buttons.

**The confirm screen is the deliberate exception.** It asks one question per
recognised dish — which source should supply this dish's numbers — and its Log
button stays `disabled` until every dish has an answer. Enabling it is the one
job that needs scripting, so with JavaScript off that screen cannot be
submitted. The rule it enforces is not a client-side one: the server refuses to
log a dish with no chosen source regardless
(`tests/Feature/ConfirmScreenTest.php`). The button is there so the screen
explains itself rather than failing quietly.

Barcode *scanning* needs scripting too, for the same reason it needs a modern
browser — it is a browser API. Typing the number never does.

### The typeface

**Manrope** (SIL Open Font License; the licence travels with the files in
`public/fonts/OFL.txt`). Three weights ship — 400, 600, 800 — in the Latin and
Cyrillic subsets only; Latin-ext and Vietnamese are dropped to keep the payload
small on mobile data, where the app is used.

Cyrillic is why it is Manrope at all. The design build asks for Plus Jakarta
Sans, which has no Cyrillic — half this interface would have fallen back to
whatever the device had lying around. Manrope covers both alphabets in one file
set, and is close enough in proportion that the build's measurements carried
over unchanged.

It is self-hosted rather than fetched from a font CDN at runtime, because a
third-party request from a page that logs what someone eats is a leak the user
did not ask for. That is the same reasoning that keeps thumbnails out of the
library.

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

See `.env.example` for the full list.

## Deploying (Railway)

The same `Dockerfile` that runs it locally runs it on [Railway](https://railway.com):
connect the repository, add a volume, set the variables, deploy. It is served by
`php artisan serve` — adequate for a handful of accounts, not a high-traffic
host.

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
in the entrypoint). A single instance on a volume has no migration race, and a
failed migration fails the deploy — the container exits and Railway keeps the
previous version serving.

What it does **not** do is leave the database as it was. Laravel wraps schema
changes in a transaction only for the grammars that declare support for it —
Postgres and SQL Server — and SQLite is not one of them. On top of that, SQLite
cannot alter a column or an index in place: adding a constraint rebuilds the
whole table (create a temporary copy, move the rows, drop, rename). A migration
that fails halfway through a set therefore leaves a partly changed schema, and
`php artisan migrate:rollback` is not an honest undo for it.

So the entrypoint **backs the database up before migrating it**, and for a file
database that copy is the real rollback:

- Only when migrations are actually pending, so a plain restart costs nothing.
- Written to `backups/` **beside the database file** — derived from
  `dirname "$DB_DATABASE"`, so it always lands on the same volume the database
  is on, whatever that volume is mounted as. It is under `storage/`, which is
  not the document root, so it is not reachable over HTTP.
- **One per day, first one wins.** If a migration fails and the platform
  restarts the container, the retry must not copy the half-migrated database
  over the good copy taken minutes earlier.
- Copied to a temporary name and renamed into place, so an interrupted copy
  cannot later be mistaken for a complete backup.
- Seven kept, oldest pruned.

**To restore**, open a Railway shell and put the file back:

```bash
ls -l /app/storage/app/backups
cp /app/storage/app/backups/food-diary.sqlite.YYYY-MM-DD /app/storage/app/food-diary.sqlite
```

then redeploy the previous release. Do this *before* redeploying, not after —
the entrypoint migrates on start.

A backup on the same volume protects against a bad migration, not against
losing the volume. Before a migration that changes existing data, take a copy
off the machine as well.

**Variables to set** in the service (Settings → Variables):

| Variable | Value |
| --- | --- |
| `APP_KEY` | generate once locally: `php artisan key:generate --show` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://<your-app>.up.railway.app` |
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

**Health check.** The app exposes `/up` (returns 200, and it is the one path
that needs no account). Point Railway's health check path at `/up` if you enable
one.

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
