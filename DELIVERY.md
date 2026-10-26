# Draft Duel — Phase 1

Working Laravel 11 / Blade / Tailwind 3 / Alpine application. All application files are in this repository; no code fragments need to be copied from chat.

## Football draft + anime power update

- Anime creation accepts **1–1000 power**. Existing saved ratings are preserved; no automatic rescaling. The power column is now an unsigned small integer and anime rarity/score bars use the 1000-point scale.
- Football has its own library and endpoints: `GET/POST /football/cards`, `POST /football/cards/{card}`. Creation requires **name, image, OVR 1–100, natural position, and an Icon checkbox**. Anime and football uploads/edits cannot cross libraries, even when names match.
- Football uses a standard flat **4-3-3**: GK, LB, CB, CB, RB, CM, CM, CM, LW, ST, RW. Eleven rounds give both participants eleven players. Each round draws two distinct players from exactly that natural position. GK appears once; CB appears twice and CM three times to fill separate slots.
- Minimum pool: **2 GK, 2 LB, 4 CB, 2 RB, 6 CM, 2 LW, 2 ST, 2 RW** (22 unique players). Setup shows missing players per position. The server refuses incomplete pools; anime cards never substitute for missing football players.
- Team pitches show each pick in its assigned formation slot. Final results compare exact summed OVR and display average team OVR / 100. Icons add a gold visual treatment and a confetti/glow reveal, **no numerical bonus**. Reduced motion disables the animated effect. A hidden player's Icon flag remains private until reveal.
- `FormationDomainContract` allows a domain to supply its round slots without adding football-specific HTTP/UI dependencies to the engine. Full formation metadata and card snapshots are persisted. Football currently enforces two participants; the DTO and generic draft engine still support N-player data.
- Run `php artisan migrate` to apply `2026_10_26_000002_add_football_card_fields.php` (already applied in this workspace). A local SQLite backup was saved under `storage/app/backups/` before migration. Existing cards and games remain intact.
- New PHP coverage: complete 11-round football game, position counts, unique IDs, active-player rotation, private Icon flags, immutable position/OVR snapshots, draws, missing positions, fixed round count, separate libraries and rating limits.
- Card flight copies are static, inert DOM nodes, avoiding Alpine scope errors. Awaited animations have a completion fallback so an interrupted effect cannot leave controls locked.

Football preview data used for browser checks lives only in `storage/app/testing/football-preview.sqlite`, with a separate session cookie; no sample football players are added to your real card library.

## Personal card library update

**My cards** is now the default deck. Use **Create cards** on the home screen or **My cards** in the header to upload a picture, enter a name, and assign power from 1–1000. Click a saved card to edit it; replacing its picture is optional. Uploads accept JPG, PNG and WebP, up to 5 MB and 4096 × 4096 pixels. Files are stored under `storage/app/private/cards` and served by a controlled image route, so no storage symlink is required on Windows.

Custom games need 6 / 10 / 14 cards for 3 / 5 / 7 rounds. Only your custom cards are included; no series bonus applies. The original Anime cards remain available through **Demo deck**. Coming-soon tiles and decorative labels have been removed. Tablet portrait and landscape layouts use larger controls, a two-column library/editor, and more compact landscape game screens.

Added files: `app/Domains/CustomDomain.php`, `app/Http/Controllers/CardController.php`, `app/Http/Requests/SaveCardRequest.php`, `resources/views/partials/card-library.blade.php`, and `tests/Feature/CardLibraryTest.php`. No database migration is needed; custom cards use the existing table with `domain = custom`.

API: `GET /cards`, `POST /cards` and `POST /cards/{card}` (multipart image/name/power), `GET /card-images/{filename}`, and versioned `POST /game/abandon`. This is a shared local library; add authenticated ownership and policies before online hosting. The library is locked during a session's active duel. Opening it requires explicitly ending that duel first. New games persist an entire private deck snapshot to prevent later edits from affecting a game. Old images are retained for saved game snapshots. Existing games without a deck snapshot still resume.

The empty-session API now returns actual JSON `null`, avoiding a blank arena on first visit. The Alpine adapter also rejects invalid empty state objects. Browser verification covered 1024 × 768 and 768 × 1024 tablet layouts and a 375 × 812 phone fallback: no horizontal overflow, 56px tablet action controls, working image preview, no console errors. **25 PHP tests passed (368 assertions), plus 3 JavaScript gesture tests**, plus the production build and Blade compilation. Automated tests cover upload validation, editing, image serving, deck freezing, custom-only scoring, library privacy and abandonment. The initial-delivery browser limitation below is historical; the in-app browser was available for this update.

## 1. Folder structure

```text
app/
  Domains/                 Domain contract, registry and implementations
  Enums/                   GamePhase
  Game/                    State DTO, engine, persistence, public transformer
  Http/Controllers/        Thin HTTP adapter
  Http/Requests/           Start, action and pick validation
  Models/                  Card, Game (plus Laravel User scaffold)
  Providers/               Domain registration
database/
  factories/               CardFactory, GameFactory
  migrations/              Laravel defaults plus draft tables
  seeders/                 Idempotent 40-card catalog
public/
  images/cards/            40 original local SVG portraits
  sounds/                  5 local synthesized WAV cues
resources/
  css/                     Palette, responsive arena and motion rules
  js/                      Transport, Alpine component and animations
  views/                   App layout, game screens and 7 components
scripts/                   Reproducible local asset generator
tests/
  Unit/                    Transport-free engine tests
  Feature/                 Session, persistence, validation and privacy tests
```

## 2. Migrations, models, factories and seeders

- `database/migrations/2026_10_26_000001_create_draft_tables.php`: UUID-keyed games, JSON state, optimistic version, indexed status; cards catalog and unique domain/name constraint.
- `app/Models/Card.php`: integer powers, JSON metadata, immutable round snapshot and derived rarity.
- `app/Models/Game.php`: UUID primary key and JSON state casts.
- `database/factories/CardFactory.php`, `database/factories/GameFactory.php`: usable model factories.
- The former Anime demo and its portraits have been removed. Character cards are user-created.
- `database/seeders/DatabaseSeeder.php`: calls only the football seeder.
- `scripts/generate-assets.mjs`: creates all 40 SVG portraits and all five WAV sounds. Generated assets are already included. Run `node scripts/generate-assets.mjs` to regenerate them.

Laravel's standard users, cache, jobs, and sessions migrations are retained. Players are stored only inside game JSON.

## 3. Domain system

- `app/Domains/DomainContract.php`: exactly the six requested methods, with `Card` and `Collection` types.
- `app/Domains/DomainRegistry.php`: registered/enabled domains; rejects unavailable domains.
- `app/Domains/AnimeDomain.php`: random draws excluding every previous card; non-stacking series bonus.
- `app/Domains/FootballDomain.php`: active 4-3-3 domain, eleven formation slots and per-position inventory validation.
- `app/Providers/AppServiceProvider.php`: registers custom character cards and Football.

Series bonus: at least two cards sharing a series gives **one** bonus of `floor(basePower * 0.10)`. Multiple matching series do not stack the bonus. Rarity is legendary above 85, epic above 65, rare otherwise.

## 4. DTO, enum, engine and tests

- `app/Enums/GamePhase.php`: Setup, PassDevice, Picking, Revealing, Finished.
- `app/Game/GameState.php`: JSON-serializable DTO containing players, domainKey, rounds, currentRound, phase, startingPlayerIndex, picks, seed, roundLimit, timerEnabled, pickDeadline and results.
- `app/Game/GameEngine.php`: startGame, drawRound, pick, revealRound, finish; also nextRound and timeout. No controllers, sessions, HTTP responses or views. Catalog access is behind the domain contract. An injected clock makes timeout tests deterministic.
- `app/Game/GameStore.php`: transaction, row lock, version comparison and conditional update. Stale commands return 422 rather than applying twice.
- `tests/Unit/GameEngineTest.php`: flow, alternating starters, no reuse, totals, series bonus, draws, public-state privacy, DTO round-trip, wrong actor, duplicate picks, phase rejection, timeout and 3-player rotation.
- `tests/Feature/GameFlowTest.php`: full HTTP game, locked-pick resume, hidden selection, wrong actor, stale writes, unavailable domains, name validation, replacement protection, session isolation, timed resume and seed idempotency.
- `tests/TestCase.php`, `phpunit.xml`: tests use in-memory SQLite and do not require Vite to be running.

The engine supports N players. For N > 2 it rotates the active index and gives the unchosen card to the next player cyclically. Only two-player settings and UI are exposed in Phase 1. The random seed is server-only game metadata; draw results are persisted, not regenerated from the seed. Deterministic replay can use the persisted rounds.

## 5. HTTP adapter, requests and public view

- `routes/web.php`: `/`, `/setup`, `GET /game/state`; `POST /game/start`, `/ready`, `/pick`, `/reveal`, `/next`, `/finish`, `/timeout`.
- `app/Http/Controllers/GameController.php`: session UUID lookup, engine invocation, filtered response. All command routes use session locks, CSRF protection, and throttling.
- `app/Http/Requests/StartGameRequest.php`: two distinct names, trim, 2–20 characters, Anime only, 3/5/7 rounds, boolean timer.
- `app/Http/Requests/GameActionRequest.php`: required version.
- `app/Http/Requests/PickRequest.php`: active player index and 48-character selection token.
- `app/Game/PublicGameView.php`: explicit allowlist, no raw DTO serialization to HTTP, no seed or private picks, only revealed recap entries. Hidden cards contain **only** `selectionId` and `hidden: true`. No hidden ID, name, power, series, image path, rarity or metadata is included.
- `bootstrap/app.php`: domain violations become JSON 422 responses.

`pick(playerIndex, cardId)` takes an opaque per-round selection token as `cardId`, never a catalog ID. A locked pick remains in Picking until revealRound, so even its response cannot leak the hidden hero. Refresh between pick and reveal shows a recovery reveal button. Commands carry an expected version, and round-specific tokens cannot be reused in later rounds.

Timer deadlines are stored on the server. A timeout command, late valid pick, or resume request after the deadline makes the random choice server-side. No worker is needed for local play: if the browser is closed, resolution occurs on the next state request. Phase 2 needs scheduled timeout jobs for live online rooms.

Local playerIndex validation is a turn rule, not identity authentication: both people share the same session/device. Phase 2 must derive player identity from credentials rather than trusting that field. Do not expose the raw `games.state` column through future room events or serializers.

## 6. Blade layout, components and screens

- `resources/views/layouts/app.blade.php`: metadata, CSRF, Bunny Fonts, Vite, decorative particle container.
- `resources/views/game.blade.php`: home, setup, pass-device, pick, reveal, results, resume and confirmation dialogs.
- `resources/views/components/card.blade.php`
- `resources/views/components/card-back.blade.php`
- `resources/views/components/player-tray.blade.php`
- `resources/views/components/score-bar.blade.php`
- `resources/views/components/turn-banner.blade.php`
- `resources/views/components/domain-tile.blade.php`
- `resources/views/components/modal.blade.php`

Picking uses double-click or double-tap on the same card, with no confirmation popup or lock-in button. A single click highlights the card. Keyboard: Tab to controls; left/right selects a card; Enter keeps it immediately; Escape closes dialogs. Dialogs trap focus and return it to the trigger. Rules and mute controls are available on every screen. Refresh restores the same game; native navigation prompts protect active games; replacement requires an explicit confirmation. Fonts fall back to local system fonts if Bunny is unavailable.

## 7. JavaScript, CSS and Tailwind

- `resources/js/gameClient.js`: **all fetch calls**; CSRF, JSON validation errors, no-store, same-origin session. Replace this adapter for online transport.
- `resources/js/draftDuel.js`: Alpine state, server resync, deadline display, confirmations, keyboard input, focus management, mute persistence and notices.
- `resources/js/animations.js`: GSAP shake/3D flip, card flight, CountUp, hover-only Vanilla Tilt, Howler, winner/confetti and neutral draw effects.
- `resources/js/app.js`: Alpine registration and lazy tsParticles slim initialization.
- `resources/css/app.css`: exact requested CSS-variable palette, responsive layouts, glass cards, rarity glow, animated score bars, reduced-motion overrides, mobile controls, skeleton and errors.
- `tailwind.config.js`: palette mapping, fonts, forms plugin and source discovery.
- `postcss.config.js`, `vite.config.js`, `package.json`, `package-lock.json`: reproducible build.

Installed packages: Tailwind, forms, Alpine, GSAP, vanilla-tilt, countup.js, canvas-confetti, `@tsparticles/engine` and `@tsparticles/slim`, Howler, Notyf, Lucide. Reduced motion disables shake, flip, flight, count-up, tilt, particles, confetti, background motion and animated score bars. Sound is muted by default; the preference persists in localStorage. No external card or audio downloads are necessary.

## 8. Setup and verification

PHP 8.2+ with SQLite/PDO, mbstring, openssl, fileinfo and other Laravel extensions; Composer; Node/npm.

```sh
composer install
cp .env.example .env
php artisan key:generate
# Create database/database.sqlite if it does not exist.
php artisan migrate --seed
npm install
npm run dev
# In another terminal:
php artisan serve --host=127.0.0.1 --port=8000
```

PowerShell on this Laragon installation:

```powershell
$env:Path = 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;' + $env:Path
php C:\laragon\bin\composer\composer.phar install
Copy-Item .env.example .env # only on a fresh checkout; preserve existing .env
php artisan key:generate # only on first setup
if (!(Test-Path database/database.sqlite)) { New-Item database/database.sqlite -ItemType File }
php artisan migrate --seed
npm install
npm run dev
# Separate terminal, with PHP on PATH:
php artisan serve --host=127.0.0.1 --port=8000
```

The delivered workspace already has dependencies installed, its local SQLite database seeded, its app key generated, and production assets built. Open `http://127.0.0.1:8000` while the server is running. Laragon virtual hosts must point to **public/**.

```sh
php artisan test
npm run build
php artisan view:cache
```

Dependency caveat: Laravel 11 was explicitly requested. Composer reported security advisories for Laravel 11.57.0 and initial resolution needed `composer update --no-security-blocking`. That was a one-command exception; the project does not globally disable Composer's security blocking. Keep this Phase 1 app local. Upgrade to a supported/patched Laravel release before exposing Phase 2 online, and run `composer audit` / `npm audit`. Current lockfiles preserve the exact tested versions.

Visual verification limitation: the in-app browser could not attach, and Windows Computer Use stopped because it could not confidently determine Chrome's URL. Automated engine/HTTP tests and the production build are verified; no desktop/mobile screenshot verification is claimed.

Verified on delivery: **16 tests passed, 101 assertions**; `npm run build` passed; `php artisan view:cache` passed; local HTTP requests to the home page, SVG artwork and WAV audio returned **200**. PHP source was formatted with Laravel Pint.

## 9. Phase 2 readiness — additions required

1. Add **rooms** and room membership/player tables: room code, host, game UUID, capacity, seat indexes, join/lifecycle status and reconnect metadata.
2. Add **per-player tokens/authentication**, room policies and private-channel authorization. Resolve the actor from credentials; never trust client playerIndex. Add player-specific public views for spectators/inactive players.
3. Install **Laravel Reverb**, broadcasting and a queue worker. Broadcast versioned `GameStarted`, `RoundReady`, `PickLocked`, `RoundRevealed`, `TurnChanged`, `GameFinished` events **after DB commit**, using only authorized public views. Keep GameEngine unchanged.
4. Add command IDs/idempotency, expected versions, reconnect state synchronization, presence/disconnect handling and server-scheduled timeout jobs that compare game version and phase.
5. Replace `gameClient.js` with authenticated room commands and Reverb subscriptions using the same public-state shape. Keep the HTTP snapshot endpoint for reconnect recovery.
6. Replace the **pass-device screen** with a room lobby, ready state, waiting-for-opponent view and private turn view. Select the two recipients explicitly if future N-player rules differ from cyclic recipients.
7. Configure TLS, production secrets, secure session/token handling, broadcast origin/channel restrictions, monitoring and supported framework/dependency versions before public deployment.

Football verification: an entire 11-round browser game completed with 22 filled formation slots and no console errors on the final build. Tablet portrait layout has no horizontal overflow. Production Vite build and Blade compilation pass. Preview players are isolated from the real library.

## Football match simulation

After the last football reveal, Kick off resolves a deterministic server-side match from the frozen lineups and game seed. Attack and midfield face the opposing midfield, defence, and goalkeeper; stronger ratings increase scoring chances without guaranteeing victory. Each side gets nine chances. The persisted goals and event timeline decide the winner (level scores are draws). Finishing twice is rejected, and refresh restores the saved result.

The browser presents the timeline over eight seconds with pitch movement, score changes, goal effects and existing sound/winner effects. Reduced-motion preferences disable movement and flashing. Anime continues to use power totals. Finished games created before this feature keep their original results.

## Character collections

Custom character cards use the existing `series` field as their collection (for example Naruto, One Piece, or DC Heroes). The creator supports free-text names and existing collection suggestions. Setup selects all custom cards or one collection; library filtering uses the same names. DC Heroes starts empty until cards are assigned to it. No schema migration is needed.

The engine receives an optional collection, uses a fresh scoped CustomDomain to draw only matching cards, and freezes the deck. GameState persists the selection for refresh and play-again. Unassigned existing cards remain available in All cards. Character cards and football remain separate. A collection needs 6/10/14 cards for 3/5/7 rounds.
