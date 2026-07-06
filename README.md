# Piplio Backend

TYPO3 extension for managing German learning content and delivering it to the Piplio app via JSON API.

The extension provides:

- a backend module for content overview
- a custom database table for learning words and related metadata
- a TYPO3 frontend plugin for a floating interest widget with TYPO3 lead storage
- a protected API endpoint for app access
- a CLI seed command for initial data

## Features

- Manage word data in TYPO3
- Manage the learning-package/topic catalog and grade recommendations in TYPO3
- Manage milestone/streak badge definitions in TYPO3
- Collect name/email interest leads directly in TYPO3 without newsletter dispatch
- Filtered API for app requests by `topic` and `difficulty`
- Catalog API for packages/topics and grade recommendations
- Catalog API for badges
- API key protection via TYPO3 Extension Settings
- Support for multiple learning types:
  - articles
  - rhymes
  - uppercase/lowercase
  - word types
  - plural forms
- seed commands for demo or initial data (words, topics/packages, badges)

## Supported Topics

The API supports these `topic` values:

- `deutsch_artikel`
- `deutsch_reime`
- `deutsch_gross_klein`
- `deutsch_wortarten`
- `deutsch_plural`

The API supports these `difficulty` values:

- `easy`
- `medium`
- `hard`

### Supported `colorKey` values

`tx_pipliobackend_topic.color_key` is a fixed select with exactly these 19 values (they map to hardcoded icon/color themes in the app; an unmatched value still works but renders a generic fallback):

```
numbers20, addition20, subtraction20, numbers100, addition100, subtraction100,
make100, bridge100, times2_5_10, times3_4, division_intro, wall_math, clock, money,
deutsch_artikel, deutsch_reime, deutsch_gross_klein, deutsch_wortarten, deutsch_plural
```

### Supported badge `icon` values

`tx_pipliobackend_badge.icon` is a fixed select with exactly these 15 values (an unmatched value still works but renders a generic fallback icon):

```
rocket, star, star2, trophy, flame, flame2, ice_cream, movie, moon, game,
crown, shield, lightning, diamond, book
```

## Installation

### Option 1: Local path repository

If the extension lives inside `packages/piplio_backend`, use a Composer path repository in the TYPO3 project:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "./packages/*"
    }
  ],
  "require": {
    "aistea/piplio-backend": "@dev"
  }
}
```

### Option 2: Git repository

If the extension is hosted in GitHub:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:AisteaTypo3/piplio-backend.git"
    }
  ],
  "require": {
    "aistea/piplio-backend": "dev-main"
  }
}
```

Then install/update:

```bash
composer update aistea/piplio-backend
```

## TYPO3 Setup

After installing the package:

1. Install or activate the extension in TYPO3 if needed.
2. Run database schema updates in the Install Tool.
3. Clear TYPO3 caches.
4. Open Extension Settings and configure the API key.
5. Configure `interestStoragePid` if leads should be stored on a dedicated sysfolder/page. If left at `0`, leads are stored on the page where the frontend plugin is placed.

## Extension Settings

The API key is configured via TYPO3 Extension Settings.

Field:

- `apiKey`
- `interestStoragePid`

These values are read by the extension for API access and lead storage.

Important:

- Do not hardcode the key in the extension code.
- Do not commit real keys into Git.
- Treat the key as an access token, not as a true secret.

## Backend Module

The extension provides a backend module under `Web`.

The module shows:

- total number of records
- visible vs hidden records
- total number of stored interest leads
- the latest frontend interest entries
- counts by topic
- counts by difficulty
- filter options
- API examples
- table overview of the word data

Use this module to inspect the current dataset quickly.

Note: this custom module currently only covers `tx_pipliobackend_word` and `tx_pipliobackend_interest`. Packages, topics, grade recommendations and badges have no dedicated module yet — manage them via TYPO3's standard **Web > List** module (see the tutorials below).

## Data Model

The extension stores data in:

- `tx_pipliobackend_word`
- `tx_pipliobackend_interest`
- `tx_pipliobackend_package`
- `tx_pipliobackend_topic`
- `tx_pipliobackend_graderecommendation`
- `tx_pipliobackend_badge`

Interest fields:

- `name`
- `email`
- `page_title`
- `page_url`
- `source_page_id`

Word fields (`tx_pipliobackend_word`):

- `topic`
- `difficulty`
- `word`
- `artikel`
- `word_type`
- `is_nomen`
- `plural_form`
- `rhyme_words`
- `no_rhyme_words`
- `wrong_options`
- `hidden`

Package fields (`tx_pipliobackend_package`):

- `package_id` — immutable join key, referenced by `topic.package` and `graderecommendation.package`
- `title`
- `description`
- `recommended_grade` — `1`, `2`, or `3`
- `hidden`

Topic fields (`tx_pipliobackend_topic`):

- `topic_id` — immutable join key, referenced by the app's locally stored `topicProgress` and topic-mastery badges
- `title`
- `subtitle`
- `color_key` — fixed select, one of the 19 values listed under [Supported `colorKey` values](#supported-colorkey-values)
- `sort_order` — ascending; gaps are fine
- `package` — relation to a `tx_pipliobackend_package` record on the same page
- `hidden`

Grade recommendation fields (`tx_pipliobackend_graderecommendation`):

- `grade` — `1`, `2`, or `3`
- `package` — relation to a `tx_pipliobackend_package` record on the same page
- `sorting` — order within the grade's package list
- `hidden`

One row = "this package is auto-enabled for this grade". A package can have several rows (one per grade it should be recommended under).

Badge fields (`tx_pipliobackend_badge`):

- `badge_id` — immutable join key, referenced by the app's locally stored `earnedBadges`
- `category` — `milestone` or `streak`
- `title`
- `description`
- `icon` — fixed select, one of the 15 values listed under [Supported badge `icon` values](#supported-badge-icon-values)
- `xp_required` — leave empty if unused
- `streak_required` — leave empty if unused
- `total_sessions_required` — leave empty if unused
- `hidden`

Set **exactly one** of `xp_required` / `streak_required` / `total_sessions_required` per badge.

## API

### Frontend Plugin

The extension now includes the frontend plugin `Piplio Interesse Widget`.

Place it on a page as a normal TYPO3 plugin content element. It renders a fixed widget on the right side with a name/email form and stores the submitted leads in `tx_pipliobackend_interest`.

### Endpoints

Supported endpoints:

- `/api/piplio/words`
- `/api/piplio/v1/words`
- `/api/piplio/topics`
- `/api/piplio/v1/topics`
- `/api/piplio/badges`
- `/api/piplio/v1/badges`

In production you should use the versioned endpoints, e.g.:

- `/api/piplio/v1/words`

### Authentication

The API accepts either of these headers:

```http
Authorization: Bearer YOUR_API_KEY
```

or

```http
X-Piplio-Api-Key: YOUR_API_KEY
```

If the key is missing or wrong, the API returns:

- `401 Unauthorized`

If the API key is not configured in TYPO3, the API returns:

- `503 Service Unavailable`

### Required Query Parameters

Both query parameters are required:

- `topic`
- `difficulty`

Example:

```http
GET /api/piplio/v1/words?topic=deutsch_artikel&difficulty=easy
```

If parameters are missing or invalid, the API returns:

- `400 Bad Request`

### Allowed Query Parameters

Only these query parameters are allowed:

- `topic`
- `difficulty`

Unexpected query parameters are rejected.

## API Response Formats

### 1. Articles

Request:

```http
GET /api/piplio/v1/words?topic=deutsch_artikel&difficulty=easy
```

Response:

```json
{
  "topic": "deutsch_artikel",
  "difficulty": "easy",
  "count": 40,
  "words": [
    {
      "word": "Hund",
      "artikel": "der"
    }
  ]
}
```

### 2. Rhymes

Request:

```http
GET /api/piplio/v1/words?topic=deutsch_reime&difficulty=easy
```

Response:

```json
{
  "topic": "deutsch_reime",
  "difficulty": "easy",
  "count": 15,
  "words": [
    {
      "word": "Maus",
      "rhymes": ["Haus", "raus"],
      "noRhymes": ["Ball", "Hund", "Baum", "Fisch"]
    }
  ]
}
```

### 3. Uppercase / Lowercase

Request:

```http
GET /api/piplio/v1/words?topic=deutsch_gross_klein&difficulty=easy
```

Response:

```json
{
  "topic": "deutsch_gross_klein",
  "difficulty": "easy",
  "count": 15,
  "words": [
    {
      "correct": "Hund",
      "wrong": "hund",
      "isNomen": true
    }
  ]
}
```

### 4. Word Types

Request:

```http
GET /api/piplio/v1/words?topic=deutsch_wortarten&difficulty=easy
```

Response:

```json
{
  "topic": "deutsch_wortarten",
  "difficulty": "easy",
  "count": 20,
  "words": [
    {
      "word": "Hund",
      "wordType": "Nomen"
    }
  ]
}
```

### 5. Plural

Request:

```http
GET /api/piplio/v1/words?topic=deutsch_plural&difficulty=easy
```

Response:

```json
{
  "topic": "deutsch_plural",
  "difficulty": "easy",
  "count": 18,
  "words": [
    {
      "singular": "Hund",
      "plural": "Hunde",
      "wrong": ["Hunds", "Hünde", "Hunden"]
    }
  ]
}
```

### 6. Topics catalog (`GET /api/piplio/v1/topics`)

No query parameters. Returns the full package/topic catalog plus grade recommendations in one payload. A record is only included if it (and, for topics, its related package) is not `hidden`/`deleted`. Since the app only overwrites its built-in defaults when the response is non-empty, do not remove all records to "clear" content — hide/delete the ones you don't want served instead.

Response:

```json
{
  "packages": [
    { "id": "grade1_numbers20", "title": "Zahlenraum bis 20", "description": "…", "recommendedGrade": "1" }
  ],
  "topics": [
    { "id": "numbers20", "title": "Zahlen bis 20", "subtitle": "Zählen, ordnen & vergleichen", "colorKey": "numbers20", "order": 0, "packageId": "grade1_numbers20" }
  ],
  "gradeRecommendations": {
    "1": ["grade1_numbers20", "grade1_arithmetic20", "grade1_numbers100", "alltag_1", "deutsch_1"],
    "2": ["grade2_arithmetic100", "alltag_1", "deutsch_1", "deutsch_23"],
    "3": ["grade3_multiplication_intro", "deutsch_23"]
  }
}
```

Important:

- `package.id` and `topic.id` are **immutable join keys** referenced by locally stored app progress. Never rename them once live.
- `topic.colorKey` must be one of the 19 fixed values in the TCA select field. An unknown value is not rejected by the API, but the app falls back to a generic color/icon for it.
- Adding a brand-new `topic.id` describes metadata for a topic the app doesn't know yet — the app cannot generate exercises for it without an app-side release. This endpoint edits existing topics, it does not create new playable ones.
- `gradeRecommendations` is independent of each package's `recommendedGrade` — a package can be recommended for one grade label but auto-enabled under several grades.
- Store `tx_pipliobackend_package`, `tx_pipliobackend_topic` and `tx_pipliobackend_graderecommendation` records on the **same storage page**. The `package` relation field in the topic and grade-recommendation records only lists packages on that same page.

### 7. Badges catalog (`GET /api/piplio/v1/badges`)

No query parameters. Returns only the threshold-based badges (`milestone` and `streak` categories) — topic-mastery badges, `all_rounder`, `perfect_rounds`, and voucher badges are generated/awarded entirely in the app and are intentionally not served here.

Response:

```json
{
  "badges": [
    { "id": "xp_50", "category": "milestone", "title": "Fleißige Biene", "description": "50 XP gesammelt", "icon": "star", "xpRequired": 50 },
    { "id": "streak_3", "category": "streak", "title": "3 Tage am Ball", "description": "3 Tage hintereinander gespielt", "icon": "flame", "streakRequired": 3 }
  ]
}
```

Important:

- `badge.id` is immutable — local `earnedBadges` progress is keyed by it.
- `badge.icon` must be one of the 15 fixed values in the TCA select field.
- Exactly one of `xpRequired`, `streakRequired`, `totalSessionsRequired` should be set per badge. A field left empty in TYPO3 is omitted from the JSON entirely (never sent as `0`), since `0` would mean "award immediately".

## Backend Content Maintenance

You can add new content in three ways:

- directly in TYPO3 backend (`Web > List`, or the custom module for words/interest)
- via CLI seed command
- via SQL import

For normal daily work, use TYPO3 backend editing.

This applies to all content types managed by this extension: words, packages, topics, grade recommendations, and badges.

## Step-by-Step Tutorial

### Tutorial 1: Install the extension

1. Add the Composer repository and package requirement.
2. Run:

```bash
composer update aistea/piplio-backend
```

3. Open TYPO3 Install Tool.
4. Apply database schema changes.
5. Clear caches.

### Tutorial 2: Configure the API key

1. Log into TYPO3 backend as admin.
2. Open `Admin Tools`.
3. Open `Settings` or Extension Configuration area, depending on the TYPO3 backend layout.
4. Find the extension `piplio_backend`.
5. Enter a value in `apiKey`.
6. Save the configuration.

Recommended:

- use a long random string
- do not use simple words

Example:

```txt
f1a8f54cce4a0f8b8e9c3c0c7db3d2d6f5f4a1f2d3e4b5c6a7b8c9d0e1f2a3b4
```

### Tutorial 3: Seed initial demo data

Run:

```bash
vendor/bin/typo3 piplio:seed --pid 1
```

If data already exists, the command does not overwrite it automatically.

To replace existing data intentionally:

```bash
vendor/bin/typo3 piplio:seed --force --truncate --pid 1
```

Warning:

- `--force --truncate` is destructive
- only use it when you really want to replace the full dataset

To seed the topics/packages catalog and the badges catalog:

```bash
vendor/bin/typo3 piplio:seed-topics --pid 1
vendor/bin/typo3 piplio:seed-badges --pid 1
```

Both support the same `--force`/`--truncate`/`--pid` options as `piplio:seed`.

### Tutorial 4: Add a new article record in TYPO3

1. Open the page/folder where your Piplio records are stored.
2. Create a new record of table `Piplio Wort`.
3. Set:
   - `Thema` = `Artikel (der/die/das)`
   - `Schwierigkeitsgrad` = `Leicht`, `Mittel`, or `Schwer`
   - `Wort` = for example `Hund`
   - `Artikel` = `der`
4. Save the record.
5. Make sure the record is not hidden.

### Tutorial 5: Add a new rhyme record

1. Create a new `Piplio Wort` record.
2. Set:
   - `Thema` = `Reime`
   - `Schwierigkeitsgrad` = desired level
   - `Wort` = e.g. `Maus`
   - `Reimwörter` = `Haus,raus`
   - `Nicht-Reimwörter` = `Ball,Hund,Baum`
3. Save the record.

### Tutorial 6: Add a new uppercase/lowercase record

1. Create a new `Piplio Wort` record.
2. Set:
   - `Thema` = `Groß- & Kleinschreibung`
   - `Wort` = e.g. `Hund` or `laufen`
   - `Ist ein Nomen` = enabled for nouns, disabled for verbs/adjectives
3. Save the record.

The API generates:

- `correct`
- `wrong`
- `isNomen`

from these values.

### Tutorial 7: Add a new word type record

1. Create a new `Piplio Wort` record.
2. Set:
   - `Thema` = `Wortarten`
   - `Wort` = e.g. `laufen`
   - `Wortart` = `Verb`
3. Save the record.

### Tutorial 8: Add a new plural record

1. Create a new `Piplio Wort` record.
2. Set:
   - `Thema` = `Plural (Mehrzahl)`
   - `Wort` = singular, e.g. `Hund`
   - `Mehrzahl (Plural)` = `Hunde`
   - `Falsche Antworten` = `Hunds,Hünde,Hunden`
3. Save the record.

### Tutorial 9: Add a new learning package

1. Open `Web > List`, navigate to the storage page/folder used for Piplio records.
2. Create a new record of table `Piplio Package`.
3. Set:
   - `Package ID` = a short, unique, lowercase/underscore id, e.g. `grade1_shapes` — **choose it carefully, it becomes an immutable join key** once topics or grade recommendations reference it, and once the app has any locally enabled-package state referencing it.
   - `Title` = e.g. `Formen erkennen`
   - `Description` = free text
   - `Recommended Grade` = `1`, `2`, or `3` (the label shown as "empfohlen für Klasse X"; this alone does **not** auto-enable the package for that grade — see Tutorial 11)
4. Save the record and make sure it is not hidden.
5. A package with no topics assigned to it is harmless but pointless — continue with Tutorial 10 to add topics to it.

### Tutorial 10: Add or edit a topic

1. Create (or open) a record of table `Piplio Topic`.
2. Set:
   - `Topic ID` — **only use an id the app already knows** (see [Supported `colorKey` values](#supported-colorkey-values) for the full current list of ids/keys). Inventing a brand-new topic id here does **not** create a playable topic — the app has no exercise generator bound to it and the topic would fail to load. Use this table to edit metadata (title, subtitle, order, package assignment, visibility) of existing topics, not to add new ones.
   - `Title` / `Subtitle` = free text
   - `Color Key` = pick from the fixed 19-value select (defaults to matching the topic id)
   - `Sort Order` = an integer; topics are sorted ascending, gaps are fine
   - `Package` = select an existing `Piplio Package` record **on the same storage page**
3. Save and make sure the record is not hidden.
4. To temporarily remove a topic from the app without losing its data, tick `Hidden` rather than deleting it — deleting orphans any locally stored progress tied to that `topic_id`.

### Tutorial 11: Set or adjust grade recommendations

`gradeRecommendations` (which packages get auto-enabled when a parent picks a grade) is separate from a package's own `Recommended Grade` label, and a package can be recommended under more than one grade.

1. Create a record of table `Piplio Grade Recommendation` for each `(grade, package)` pair you want.
2. Set:
   - `Grade` = `1`, `2`, or `3`
   - `Package` = the `Piplio Package` record to auto-enable for that grade
3. Save. Repeat for every grade the package should appear under (e.g. a package usable in both grade 1 and grade 2 needs two records, one per grade).
4. To remove a package from a grade's recommendations, hide or delete the corresponding row — this does not affect the package's own `Recommended Grade` label or its topics.

### Tutorial 12: Add a new badge

1. Create a record of table `Piplio Badge`.
2. Set:
   - `Badge ID` = a short, unique, immutable id, e.g. `xp_100000` — local `earnedBadges` progress is keyed by this once players earn it.
   - `Category` = `milestone` or `streak` (these are the only two categories served by the API — see [What NOT to serve](#what-not-to-serve-from-typo3) below)
   - `Title` / `Description` = free text
   - `Icon` = pick from the fixed 15-value select
   - Set **exactly one** of `XP required`, `Streak days required`, `Total sessions required` — leave the other two empty. A `0` in an unused field would make the badge award immediately, so leave it blank, not zero.
3. Save and make sure the record is not hidden.

#### What NOT to serve from TYPO3

Some badge kinds are generated or awarded with custom logic in the app and must stay app-side — do not try to recreate them as `tx_pipliobackend_badge` records:

- **Topic-mastery badges** ("Themen-Meister") — one is generated automatically per topic at 3 stars; no TYPO3 record needed.
- **`all_rounder`** — awarded when every enabled topic has been played once; hardcoded condition, ignores threshold fields.
- **`perfect_rounds`** — a stacking counter with custom logic; also hardcoded.
- **Voucher badges** — parent-defined custom rewards created locally in the app, never server-side.

### Tutorial 13: Hide vs. delete — and the "empty response" rule

The app only overwrites its bundled local defaults when an API response is **non-empty**. Practical implications:

- To temporarily remove a topic, package, or badge from what the app sees, tick `Hidden` on that record. Do **not** delete/hide every record of a type hoping to "reset" the app to defaults — an empty `topics`/`badges` array (or a `packages` array that leaves referenced ids missing) is simply **ignored**, not applied.
- Never rename or reuse a `package_id`, `topic_id`, or `badge_id` that has ever gone live — they are immutable join keys for locally stored app progress (enabled packages, topic progress, earned badges). Renaming orphans that local data.
- If you need to remove a topic id from `data/topics.ts` generator support first, coordinate with an app release — see Tutorial 10.

### Tutorial 14: Test the API manually

Example with Bearer token:

```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
  "https://your-domain.tld/api/piplio/v1/words?topic=deutsch_artikel&difficulty=easy"
```

Example with custom header:

```bash
curl -H "X-Piplio-Api-Key: YOUR_API_KEY" \
  "https://your-domain.tld/api/piplio/v1/words?topic=deutsch_plural&difficulty=medium"
```

Topics and badges catalogs (no query parameters):

```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
  "https://your-domain.tld/api/piplio/v1/topics"

curl -H "Authorization: Bearer YOUR_API_KEY" \
  "https://your-domain.tld/api/piplio/v1/badges"
```

### Tutorial 15: Troubleshooting

#### Problem: `401 Unauthorized`

Check:

- is the API key configured in TYPO3?
- is the request sending the correct header?
- is the key exactly correct?

#### Problem: `400 Bad Request`

Check:

- are both `topic` and `difficulty` present?
- is `topic` one of the allowed values?
- is `difficulty` one of `easy`, `medium`, `hard`?
- are you sending extra query parameters?

#### Problem: `503 API key is not configured`

Check:

- was the API key saved in Extension Settings?
- were TYPO3 caches cleared after configuration?

#### Problem: empty `words` array

Check:

- are matching records in TYPO3 present?
- are records hidden?
- are records malformed and being skipped?
- check TYPO3 logs for skipped invalid rows

#### Problem: a topic doesn't show up in `/api/piplio/v1/topics`

Check:

- is the topic record hidden or deleted?
- is its assigned `Package` record hidden, deleted, or on a **different storage page**? Topics are only returned if their package is also visible, and the package relation dropdown only lists packages on the same page.
- does the topic have `topic_id`, `title`, and a resolvable `package` — all three are required or the row is skipped (check TYPO3 logs for a "Skipped invalid Piplio topic rows" warning).

#### Problem: a package is missing from `gradeRecommendations`

Check:

- does a `Piplio Grade Recommendation` record exist for that `(grade, package)` pair? A package's own `Recommended Grade` field does **not** by itself add it to `gradeRecommendations` — see Tutorial 11.
- is the grade-recommendation record (or its linked package) hidden/deleted?

#### Problem: a badge is missing or shows a generic fallback icon/color

Check:

- for a missing badge: is it hidden/deleted, or missing `badge_id`/`title`/`icon`, or does it have a `category` other than `milestone`/`streak`? (Check TYPO3 logs for "Skipped invalid Piplio badge rows".)
- for a fallback icon: is `icon` one of the exact 15 supported values? A typo or unsupported value doesn't error, it just renders generically in the app.
- did you intend to add `all_rounder`, `perfect_rounds`, a topic-mastery badge, or a voucher? Those are intentionally not served from TYPO3 — see [What NOT to serve](#what-not-to-serve-from-typo3).

#### Problem: a new topic doesn't work in the app even though the API returns it

This is expected if the `topic_id` is new (not one of the ids the app's exercise generators already support). This endpoint edits metadata for topics the app already knows — see Tutorial 10. Adding a genuinely new, playable topic requires an app-side code release.

## Security Notes

- Serve the API only via HTTPS
- Protect the endpoint with rate limits on webserver, proxy or CDN level
- The API key is an access token, not a true secret
- Do not store real secrets in Git
- Do not add private data to word records

## Production Recommendations

- use the versioned endpoints: `/api/piplio/v1/words`, `/api/piplio/v1/topics`, `/api/piplio/v1/badges`
- keep the API key configured in TYPO3 backend
- enable server-side HTTPS redirect
- add rate limiting per IP or token
- monitor TYPO3 logs for invalid rows
- avoid using destructive seeding on production unless intentional
- store `tx_pipliobackend_package`, `tx_pipliobackend_topic`, and `tx_pipliobackend_graderecommendation` records on the same storage page

## Updating Data Safely

For production content changes:

1. Prefer TYPO3 backend editing.
2. Test API output after changes.
3. Avoid `--force --truncate` unless you want full replacement.
4. Keep records consistent with the required topic format.
5. Never rename or delete a `package_id`, `topic_id`, or `badge_id` that has ever gone live — hide it instead (see Tutorial 13).
6. Only use the fixed `colorKey`/`icon` values — anything else silently falls back in the app instead of erroring.
7. Remember the "non-empty response only" rule: emptying a table to "reset" the app to defaults does nothing — the app just ignores the empty response and keeps its last-known-good data.

## Files in This Extension

- [composer.json](/Users/aistea/PhpstormProjects/portfolio-ais/packages/piplio_backend/composer.json:1)
- [ext_conf_template.txt](/Users/aistea/PhpstormProjects/portfolio-ais/packages/piplio_backend/ext_conf_template.txt:1)
- [ext_tables.sql](/Users/aistea/PhpstormProjects/portfolio-ais/packages/piplio_backend/ext_tables.sql:1)
- [Classes/Middleware/ApiMiddleware.php](/Users/aistea/PhpstormProjects/portfolio-ais/packages/piplio_backend/Classes/Middleware/ApiMiddleware.php:1)
- [Classes/Middleware/TopicsApiMiddleware.php](/Users/aistea/PhpstormProjects/portfolio-ais/packages/piplio_backend/Classes/Middleware/TopicsApiMiddleware.php:1)
- [Classes/Middleware/BadgesApiMiddleware.php](/Users/aistea/PhpstormProjects/portfolio-ais/packages/piplio_backend/Classes/Middleware/BadgesApiMiddleware.php:1)
- [Classes/Command/SeedWordsCommand.php](/Users/aistea/PhpstormProjects/portfolio-ais/packages/piplio_backend/Classes/Command/SeedWordsCommand.php:1)
- [Classes/Command/SeedTopicsCommand.php](/Users/aistea/PhpstormProjects/portfolio-ais/packages/piplio_backend/Classes/Command/SeedTopicsCommand.php:1)
- [Classes/Command/SeedBadgesCommand.php](/Users/aistea/PhpstormProjects/portfolio-ais/packages/piplio_backend/Classes/Command/SeedBadgesCommand.php:1)
- [Configuration/TCA/tx_pipliobackend_word.php](/Users/aistea/PhpstormProjects/portfolio-ais/packages/piplio_backend/Configuration/TCA/tx_pipliobackend_word.php:1)
- [Configuration/TCA/tx_pipliobackend_package.php](/Users/aistea/PhpstormProjects/portfolio-ais/packages/piplio_backend/Configuration/TCA/tx_pipliobackend_package.php:1)
- [Configuration/TCA/tx_pipliobackend_topic.php](/Users/aistea/PhpstormProjects/portfolio-ais/packages/piplio_backend/Configuration/TCA/tx_pipliobackend_topic.php:1)
- [Configuration/TCA/tx_pipliobackend_graderecommendation.php](/Users/aistea/PhpstormProjects/portfolio-ais/packages/piplio_backend/Configuration/TCA/tx_pipliobackend_graderecommendation.php:1)
- [Configuration/TCA/tx_pipliobackend_badge.php](/Users/aistea/PhpstormProjects/portfolio-ais/packages/piplio_backend/Configuration/TCA/tx_pipliobackend_badge.php:1)
- [Resources/Private/InitialData.sql](/Users/aistea/PhpstormProjects/portfolio-ais/packages/piplio_backend/Resources/Private/InitialData.sql:1)

## Summary

This extension is designed to let TYPO3 manage structured learning content and expose it in a controlled JSON format for the Piplio app.

For normal use:

- configure the API key
- maintain words, packages, topics, grade recommendations, and badges in TYPO3 backend
- test word requests with `topic` and `difficulty`, and topics/badges requests with no parameters
- protect the API with HTTPS and rate limiting
