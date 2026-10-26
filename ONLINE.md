# Player rooms and administration

The public journey is Welcome → Draft → Football / Draft Party → Rules → Setup → Lobby → Play. The player screens do not include card-management controls. Local hot-seat play remains available in Setup.

## Admin account

Create your administrator once from the project directory:

```sh
php artisan draft:admin
```

The command asks for an email and a hidden password of at least 12 characters. No default password or public registration endpoint is provided. On the **mode selection** screen, activate the small bottom-left corner three times within 1.2 seconds to open admin login. `/admin/login` is also available for keyboard/bookmarked access. Every card list, upload, and update endpoint enforces the administrator role on the server; the hidden button is only navigation.

## Run locally

```sh
composer install
npm install
php artisan migrate
npm run build
php artisan serve --host=127.0.0.1 --port=8000
# A second terminal:
php artisan reverb:start --host=127.0.0.1 --port=8080
```

The development `.env` has generated Reverb credentials. Use unique credentials in each deployment. `REVERB_APP_KEY` is public; `REVERB_APP_SECRET` must remain server-only. Reverb sends immediate events, so these room events do not require a queue worker.

To test with multiple people, use separate browser profiles/devices. Tabs in one browser session share one seat by design. A localhost invitation only works on the hosting computer. For LAN testing, bind HTTP/Reverb to the LAN interface, configure `REVERB_ALLOWED_ORIGINS` with the LAN hostname/IP, and open the site using that address. Do not use development servers as public production servers.

## Room behavior

- Rooms have unguessable UUIDs, 8-character invite codes, 2–4 seats, and a 24-hour expiry.
- Joining creates a random 256-bit seat token stored in the HTTP session; only its SHA-256 hash is stored in the room. Tokens are never included in API or WebSocket responses.
- Each online player enters their own name and readiness. The host can change lobby settings, which resets all readiness, and starts when all seats are filled and ready.
- Host-only actions: settings, start, next round, finish, shared football replay, and room closure. The picker is determined by the authenticated session, never by a supplied player index.
- The picking player sees one visible card and one opaque hidden-card token. Other players receive no current cards until reveal. All three-player automatic cards remain private until reveal.
- Picks and room changes use database transactions, row locks, and versions. Old or duplicate submissions fail without assigning cards twice.
- Reverb uses authorized private room channels. Events contain only a version number; clients fetch a viewer-specific public state. Two-second resync also recovers missed events and resolves expired pick timers.
- Match outcomes are generated once by the existing engine. The server supplies a shared playback timestamp; each device follows that clock for 15 seconds, and a reconnect catches up. Replays do not reroll outcomes.
- Refreshing the same browser restores the seat. Clearing cookies loses that seat; there is currently no account-based seat transfer. If a player is disconnected with the timer disabled, the game waits for their return. The host can close the room.
- All active games use frozen card snapshots. Admin edits affect subsequent games only. Existing local saved games use their separate session pointer.

## Production deployment gate

The project now runs on a patched Laravel 12 release; `composer audit` and `npm audit` report no advisories. Keep running both before each deployment and upgrade rather than suppress any new warning. Step-by-step VPS setup is in [DEPLOY.md](DEPLOY.md).

Production also needs HTTPS/WSS, `APP_DEBUG=false`, secure session cookies, trusted proxy settings for the chosen host, a supervised Reverb process, exact allowed origins, and persistent shared database/session/cache storage. The frontend connects to Reverb on the same hostname as the website using the configured port; a TLS reverse proxy can expose that on 443. Set the server-side Reverb host/port/scheme to the address the PHP application can reach.

See [Laravel Reverb deployment documentation](https://laravel.com/docs/11.x/reverb#running-reverb-in-production). Run `composer audit`, `npm audit`, `php artisan test`, and `npm run build` before deployment.

## Main files

- `app/Rooms/RoomAccess.php`, `RoomView.php`: seat authorization and private/public state boundary.
- `app/Http/Controllers/RoomController.php`: transactional room transport and lifecycle.
- `app/Game/*`: reusable transport-free draft and match logic.
- `app/Events/RoomChanged.php`: minimal private-channel notification.
- `resources/js/gameClient.js`: HTTP and Reverb transport.
- `resources/js/onlineRoom.js`: lobby, reconnection, and synchronized playback coordination.
- `resources/views/partials/journey.blade.php`: public onboarding and lobby.
- `app/Http/Middleware/RequireAdmin.php`: role boundary for card management.
