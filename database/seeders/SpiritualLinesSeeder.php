<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SpiritualLinesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $orixas = [
            ['name' => 'Oxalá', 'slug' => 'oxala', 'icon' => 'sun', 'color_hex' => '#e5e7eb'],
            ['name' => 'Ogum', 'slug' => 'ogum', 'icon' => 'shield', 'color_hex' => '#2563eb'],
            ['name' => 'Oxóssi', 'slug' => 'oxossi', 'icon' => 'leaf', 'color_hex' => '#16a34a'],
            ['name' => 'Xangô', 'slug' => 'xango', 'icon' => 'axe', 'color_hex' => '#b45309'],
            ['name' => 'Iemanjá', 'slug' => 'iemanja', 'icon' => 'waves', 'color_hex' => '#38bdf8'],
            ['name' => 'Oxum', 'slug' => 'oxum', 'icon' => 'droplet', 'color_hex' => '#facc15'],
            ['name' => 'Iansã', 'slug' => 'iansa', 'icon' => 'wind', 'color_hex' => '#f97316'],
            ['name' => 'Omulu', 'slug' => 'omulu', 'icon' => 'cross', 'color_hex' => '#111827'],
            ['name' => 'Nanã', 'slug' => 'nana', 'icon' => 'moon', 'color_hex' => '#a78bfa'],
            ['name' => 'Exu', 'slug' => 'exu', 'icon' => 'flame', 'color_hex' => '#ef4444'],
            ['name' => 'Pombagira', 'slug' => 'pombagira', 'icon' => 'sparkles', 'color_hex' => '#be123c'],
        ];

        foreach ($orixas as $o) {
            DB::table('spiritual_lines')->updateOrInsert(
                ['slug' => $o['slug'], 'type' => 'orixa'],
                [
                    'name' => $o['name'],
                    'parent_id' => null,
                    'description' => null,
                    'icon' => $o['icon'] ?? null,
                    'color_hex' => $o['color_hex'] ?? null,
                    'meta' => json_encode([]),
                    'status' => 'ativo',
                    'sort_order' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        $idOf = fn(string $slug) => DB::table('spiritual_lines')
            ->where('slug', $slug)->where('type', 'orixa')->value('id');

        $oxossi = $idOf('oxossi');
        $iemanja = $idOf('iemanja');
        $ogum = $idOf('ogum');
        $xango = $idOf('xango');
        $omulu = $idOf('omulu');
        $nana = $idOf('nana');
        $exuOrixa = $idOf('exu');
        $pombaOri = $idOf('pombagira');
        $oxum = $idOf('oxum');

        $linhas = [
            ['name' => 'Caboclos', 'slug' => 'caboclos', 'parent_slug' => 'oxossi', 'icon' => 'leaf', 'color_hex' => '#16a34a'],
            ['name' => 'Pretos-Velhos', 'slug' => 'pretos-velhos', 'parent_slug' => 'omulu', 'icon' => 'pipe', 'color_hex' => '#78350f'],
            ['name' => 'Baianos', 'slug' => 'baianos', 'parent_slug' => 'ogum', 'icon' => 'flag', 'color_hex' => '#0ea5e9'],
            ['name' => 'Boiadeiros', 'slug' => 'boiadeiros', 'parent_slug' => 'xango', 'icon' => 'lasso', 'color_hex' => '#92400e'],
            ['name' => 'Marinheiros', 'slug' => 'marinheiros', 'parent_slug' => 'iemanja', 'icon' => 'anchor', 'color_hex' => '#0284c7'],
            ['name' => 'Crianças / Erês', 'slug' => 'criancas-eres', 'parent_slug' => 'oxum', 'icon' => 'smile', 'color_hex' => '#f59e0b'],
            ['name' => 'Ciganos', 'slug' => 'ciganos', 'parent_slug' => null, 'icon' => 'sparkles', 'color_hex' => '#a855f7'],
            ['name' => 'Linha de Exu', 'slug' => 'linha-exu', 'parent_slug' => 'exu', 'icon' => 'flame', 'color_hex' => '#ef4444'],
            ['name' => 'Linha de Pombagira', 'slug' => 'linha-pombagira', 'parent_slug' => 'pombagira', 'icon' => 'heart', 'color_hex' => '#be123c'],
            ['name' => 'Malandros', 'slug' => 'malandros', 'parent_slug' => 'exu', 'icon' => 'cigarette', 'color_hex' => '#d97706'],
        ];

        $orixaIdBySlug = [
            'oxossi' => $oxossi, 'iemanja' => $iemanja, 'ogum' => $ogum, 'xango' => $xango,
            'omulu' => $omulu, 'nana' => $nana, 'exu' => $exuOrixa, 'pombagira' => $pombaOri, 'oxum' => $oxum,
        ];

        foreach ($linhas as $l) {
            DB::table('spiritual_lines')->updateOrInsert(
                ['slug' => $l['slug'], 'type' => 'linha'],
                [
                    'name' => $l['name'],
                    'parent_id' => $l['parent_slug'] ? ($orixaIdBySlug[$l['parent_slug']] ?? null) : null,
                    'description' => null,
                    'icon' => $l['icon'] ?? null,
                    'color_hex' => $l['color_hex'] ?? null,
                    'meta' => json_encode([]),
                    'status' => 'ativo',
                    'sort_order' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }

        // 3) Falanges (type=falange) — exemplos simples
        $falanges = [
            ['name' => 'Caboclo Pena Branca', 'parent_line_slug' => 'caboclos'],
            ['name' => 'Pai Joaquim', 'parent_line_slug' => 'pretos-velhos'],
            ['name' => 'Vovó Maria Conga', 'parent_line_slug' => 'pretos-velhos'],
        ];

        $lineId = fn(string $slug) => DB::table('spiritual_lines')
            ->where('slug', $slug)->where('type', 'linha')->value('id');

        foreach ($falanges as $f) {
            $parentId = $lineId($f['parent_line_slug']);
            $slug = Str::slug($f['name'], '-');
            DB::table('spiritual_lines')->updateOrInsert(
                ['slug' => $slug, 'type' => 'falange'],
                [
                    'name' => $f['name'],
                    'parent_id' => $parentId,
                    'description' => null,
                    'icon' => null,
                    'color_hex' => null,
                    'meta' => json_encode([]),
                    'status' => 'ativo',
                    'sort_order' => null,
                    'updated_at' => $now,
                    'created_at' => $now,
                    'deleted_at' => null,
                ]
            );
        }
    }
}
