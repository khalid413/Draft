# Draft Duel

Private online rooms and the protected admin area are implemented. See [ONLINE.md](ONLINE.md) for admin account creation, Reverb startup, room behavior, and deployment requirements.

Local hot-seat drafting for 2–4 players, built with Laravel 12, Blade, Tailwind and Alpine. Deployment: see [DEPLOY.md](DEPLOY.md).

Choose the player count in New game. Character drafts award 3, 5 or 7 cards per player. For three players, each turn has two selectable cards plus a private random third card for the remaining player; roles rotate. For four players, two rotating pairs draft each round. All cards are unique within a game.

Football gives every player a complete 4-3-3. Two players play a final; three or four play a round-robin league (3 or 6 fixtures). Wins earn 3 points, draws 1; ties use goal difference and then goals scored, with shared titles for equal records. Each match animation and replay lasts 15 seconds. Results are generated once on the server and replays never reroll them.

The starter catalog has 67 football cards, including 12 CMs to support four teams. Run `php artisan db:seed --class=FootballPlayerSeeder` to add missing seeded players without overwriting edits. No new migration is needed: schedule and cards-per-player are persisted in the existing game JSON. Older two-player games remain resumable.

Verify with `php artisan test` and `npm run build`. Multiplayer tests cover equal roster sizes, every formation slot, hidden cards, persistence, balanced pickers, league scoring and HTTP validation.

## Moroccan Carta (`/carta`)

Multiplayer Carta for 2–6 players with the 40-card Spanish/Moroccan deck (`public/cards/{suit}/{suit}_{rank}.webp`, back at `public/cards/back.webp`). Match the suit or number; **1** skips the next player, **2** makes them draw two, **7** is wild and changes the suit. 1s and 2s stack to pass the penalty on. Players finish in ranked order and the match continues until one player is left holding cards: the loser.

The server owns every rule: `app/Carta/CartaEngine.php` (state machine), `CartaStore` (games, per-seat hands and ranks, move history), `CartaView` (per-player view that never includes opponents' cards or the draw pile) and `CartaController` (locked transactions, version checks, retry-safe action ids). Rooms, seats, Reverb updates and admin bans are shared with Draft Duel through `app/Rooms/RoomLobby.php`. Tests: `tests/Unit/CartaEngineTest.php` and `tests/Feature/CartaRoomTest.php`.

**Start with [DELIVERY.md](DELIVERY.md)** for the complete ordered implementation guide, file inventory, setup commands, verification results and Phase 2 plan.

The Laravel framework information below is retained from the project scaffold.

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
