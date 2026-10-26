# Football starter roster

`FootballPlayerSeeder` adds 65 football-only cards with two Icons per position.
GK has five regular players, CM has eight, and all other positions have six.
The expansion adds two regular players to every position and 15 new Icons
alongside the original Casillas. Ratings are FC 27 base OVR, checked on
26 October 2026. Every card retains its rating source in metadata.

Sources:

- [FC 27 roster ratings and positions](https://beebom.com/ea-fc-27-player-ratings/)
- [EA: Balde, 82 LB](https://www.ea.com/es/games/ea-sports-fc/ratings/player-ratings/balde/263578)
- [Casillas base Icon, 90 GK](https://www.futwiz.com/fc27/player/casillas/111657)
- [FC 27 Icon ratings and positions](https://www.redbull.com/int-en/ea-fc-27-all-icons-legends)
- [FC 27 player database](https://www.fut.gg/players/)
- Player portraits: FUTWIZ's FC 27 face CDN. Original URLs and EA IDs are
  retained in each card's metadata. The downloaded PNGs are bundled in
  `public/images/football` so seeding and playing do not require remote requests.
  Player images remain the property of their respective rights holders.

Run only this roster with `php artisan db:seed --class=FootballPlayerSeeder`.
Normal `php artisan migrate --seed` includes it too. Existing football cards
with matching names are preserved, including any personal image/rating edits.
Three incorrect original photo references (Mendes, Cucurella, Cherki) are
repaired only when they still point to the original seeded image.
Existing games retain their original deck snapshots; start a new football game
to use the roster.
