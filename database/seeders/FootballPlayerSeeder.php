<?php

namespace Database\Seeders;

use App\Models\Card;
use Illuminate\Database\Seeder;

class FootballPlayerSeeder extends Seeder
{
    public function run(): void
    {
        // FC 27 base ratings, checked 2026-10-26. Includes two Icons
        // per position and enough unique CMs for four full 4-3-3s.
        $players = [
            ['Thibaut Courtois', 'GK', 90, 192119],
            ['Gianluigi Donnarumma', 'GK', 89, 230621],
            ['Jan Oblak', 'GK', 88, 200389],
            ['Iker Casillas', 'GK', 90, 5479],
            ['Nuno Mendes', 'LB', 89, 252145],
            ['Marc Cucurella', 'LB', 86, 239231],
            ['Federico Dimarco', 'LB', 86, 226268],
            ['Alejandro Balde', 'LB', 82, 263578],
            ['Gabriel', 'CB', 89, 232580],
            ['Willian Pacho', 'CB', 89, 256196],
            ['William Saliba', 'CB', 88, 243715],
            ['Virgil van Dijk', 'CB', 88, 203376],
            ['Achraf Hakimi', 'RB', 88, 235212],
            ['Konrad Laimer', 'RB', 85, 225375],
            ['Marcos Llorente', 'RB', 85, 226161],
            ['Jules Koundé', 'RB', 85, 241486],
            ['Pedri', 'CM', 90, 251854],
            ['Martin Ødegaard', 'CM', 86, 222665],
            ['Fabián Ruiz', 'CM', 86, 226271],
            ['Vitinha', 'CM', 90, 255253],
            ['João Neves', 'CM', 88, 272834],
            ['Federico Valverde', 'CM', 87, 239053],
            ['Nicolò Barella', 'CM', 87, 224232],
            ['Frenkie de Jong', 'CM', 86, 228702],
            ['Khvicha Kvaratskhelia', 'LW', 89, 247635],
            ['Vini Jr.', 'LW', 89, 238794],
            ['Raphinha', 'LW', 88, 233419],
            ['Bradley Barcola', 'LW', 85, 264652],
            ['Kylian Mbappé', 'ST', 91, 231747],
            ['Erling Haaland', 'ST', 91, 239085],
            ['Harry Kane', 'ST', 90, 202126],
            ['Ousmane Dembélé', 'ST', 90, 231443],
            ['Lamine Yamal', 'RW', 90, 277643],
            ['Bukayo Saka', 'RW', 87, 246669],
            ['Désiré Doué', 'RW', 86, 271421],
            ['Rayan Cherki', 'RW', 86, 251570],
            ['David Raya', 'GK', 88, 220901],
            ['Alisson', 'GK', 87, 212831],
            ['Theo Hernández', 'LB', 82, 232656],
            ['Alphonso Davies', 'LB', 82, 234396],
            ['Marquinhos', 'CB', 87, 207865],
            ['Rúben Dias', 'CB', 87, 239818],
            ['Reece James', 'RB', 84, 238074],
            ['Jurriën Timber', 'RB', 84, 251805],
            ['Bruno Guimarães', 'CM', 86, 247851],
            ['Enzo Fernández', 'CM', 86, 247090],
            ['Jérémy Doku', 'LW', 84, 246420],
            ['Nico Williams', 'LW', 84, 256516],
            ['Alexander Isak', 'ST', 86, 233731],
            ['Viktor Gyökeres', 'ST', 86, 241651],
            ['Antoine Semenyo', 'RW', 85, 241236],
            ['Mohamed Salah', 'RW', 87, 209331],
            ['Peter Schmeichel', 'GK', 89, 238428],
            ['Roberto Carlos', 'LB', 90, 238430],
            ['Marcelo', 'LB', 89, 176676],
            ['Franco Baresi', 'CB', 91, 166906],
            ['Alessandro Nesta', 'CB', 89, 1088],
            ['Cafu', 'RB', 91, 5003],
            ['Javier Zanetti', 'RB', 89, 1041],
            ['Xavi', 'CM', 91, 10535],
            ['Andrea Pirlo', 'CM', 90, 7763],
            ['Ronaldinho', 'LW', 93, 28130],
            ['Rivaldo', 'LW', 90, 4231],
            ['Ronaldo', 'ST', 94, 37576],
            ['Thierry Henry', 'ST', 91, 1625],
            ['George Best', 'RW', 90, 226764],
            ['Luís Figo', 'RW', 90, 5589],
        ];

        $iconIds = [5479, 238428, 238430, 176676, 166906, 1088, 5003, 1041,
            10535, 7763, 28130, 4231, 37576, 1625, 226764, 5589];
        $sources = [
            222665 => 'https://www.ea.com/da/games/ea-sports-fc/ratings/teams-ratings/arsenal/1',
            226271 => 'https://www.fcratings.com/fabian-ruiz-226271',
            232656 => 'https://www.ea.com/es/games/ea-sports-fc/ratings/player-ratings/theo-hernandez/232656',
            234396 => 'https://www.fcratings.com/positions/left-back-7',
            256516 => 'https://www.fut.gg/players/256516-nico-williams/27-256516/',
            209331 => 'https://www.fut.gg/players/209331-mohamed-salah/27-209331/',
            28130 => 'https://www.fut.gg/players/28130-ronaldinho/27-28130/',
        ];

        foreach ($players as [$name, $position, $ovr, $eaId]) {
            $icon = in_array($eaId, $iconIds, true);
            // Re-running the seeder preserves uploaded pictures and edited ratings.
            $card = Card::firstOrCreate(['domain' => 'football', 'name' => $name], [
                'series' => '',
                'position' => $position,
                'power' => $ovr,
                'is_icon' => $icon,
                'image_path' => '/images/football/'.$eaId.'.png',
                'meta' => [
                    'seed' => 'football-fc27',
                    'edition' => 'FC 27',
                    'ea_id' => $eaId,
                    'rating_source' => $sources[$eaId] ?? ($icon
                        ? 'https://www.redbull.com/int-en/ea-fc-27-all-icons-legends'
                        : ($eaId === 263578
                            ? 'https://www.ea.com/es/games/ea-sports-fc/ratings/player-ratings/balde/263578'
                            : 'https://beebom.com/ea-fc-27-player-ratings/')),
                    'image_source' => 'https://cdn.futwiz.com/assets/img/fc27/faces/'.$eaId.'.png',
                    'checked_at' => '2026-10-26',
                ],
            ]);

            // Repair only the original seeded photo references, never uploads.
            $oldPhotoId = [252145 => 253149, 239231 => 232656, 251570 => 251517][$eaId] ?? null;
            if ($oldPhotoId && ($card->meta['seed'] ?? null) === 'football-fc27'
                && $card->image_path === '/images/football/'.$oldPhotoId.'.png') {
                $card->update([
                    'image_path' => '/images/football/'.$eaId.'.png',
                    'meta' => array_merge($card->meta, ['ea_id' => $eaId,
                        'image_source' => 'https://cdn.futwiz.com/assets/img/fc27/faces/'.$eaId.'.png']),
                ]);
            }
        }
    }
}
