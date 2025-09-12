<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LineItemTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $lineId = function (string $slug) {
            return DB::table('spiritual_lines')->where('slug', $slug)->value('id');
        };

        $findItemId = function (string $partialName) {
            return DB::table('inventory_items')
                ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($partialName).'%'])
                ->value('id');
        };

        $templates = [
            'pretos-velhos' => [
                ['q' => 'vela branca', 'purpose' => 'oferta',     'qty' => 7,   'unit' => 'un', 'required' => true,  'notes' => 'Velas de 7 dias se possível'],
                ['q' => 'charuto',     'purpose' => 'trabalho',   'qty' => 1,   'unit' => 'un', 'required' => false, 'notes' => null],
                ['q' => 'café',        'purpose' => 'oferta',     'qty' => 1,   'unit' => 'garrafa', 'required' => false, 'notes' => null],
                ['q' => 'pemba',       'purpose' => 'trabalho',   'qty' => 1,   'unit' => 'un', 'required' => true,  'notes' => null],
                ['q' => 'arruda',      'purpose' => 'defumacao',  'qty' => 1,   'unit' => 'maco', 'required' => false, 'notes' => 'Ervas para defumação'],
            ],
            'caboclos' => [
                ['q' => 'vela verde',  'purpose' => 'oferta',    'qty' => 7,  'unit' => 'un', 'required' => false, 'notes' => null],
                ['q' => 'ervas',       'purpose' => 'defumacao', 'qty' => 1,  'unit' => 'kit', 'required' => false, 'notes' => 'Guiné/Arruda/Alecrim'],
                ['q' => 'charuto',     'purpose' => 'trabalho',  'qty' => 1,  'unit' => 'un', 'required' => false, 'notes' => null],
            ],
            'linha-exu' => [
                ['q' => 'vela vermelha', 'purpose' => 'oferta',   'qty' => 7,  'unit' => 'un', 'required' => false, 'notes' => null],
                ['q' => 'pinga',         'purpose' => 'oferta',   'qty' => 1,  'unit' => 'garrafa', 'required' => false, 'notes' => 'Aguardente'],
                ['q' => 'charuto',       'purpose' => 'trabalho', 'qty' => 1,  'unit' => 'un', 'required' => false, 'notes' => null],
                ['q' => 'pimenta',       'purpose' => 'oferta',   'qty' => 7,  'unit' => 'un', 'required' => false, 'notes' => null],
            ],
            'linha-pombagira' => [
                ['q' => 'vela vermelha', 'purpose' => 'oferta',  'qty' => 7,   'unit' => 'un', 'required' => false, 'notes' => null],
                ['q' => 'rosa vermelha', 'purpose' => 'oferta',  'qty' => 7,   'unit' => 'un', 'required' => false, 'notes' => null],
                ['q' => 'champanhe',     'purpose' => 'oferta',  'qty' => 1,   'unit' => 'garrafa', 'required' => false, 'notes' => null],
            ],
            'marinheiros' => [
                ['q' => 'vela azul',     'purpose' => 'oferta',  'qty' => 7,   'unit' => 'un', 'required' => false, 'notes' => null],
                ['q' => 'concha',        'purpose' => 'oferta',  'qty' => 3,   'unit' => 'un', 'required' => false, 'notes' => 'Enfeite/altar'],
            ],
            'malandros' => [
                ['q' => 'cigarro',        'purpose' => 'trabalho',  'qty' => 1,  'unit' => 'un',       'required' => false, 'notes' => 'Zé Pelintra e Malandros em geral'],
                ['q' => 'champanhe',      'purpose' => 'oferta',    'qty' => 1,  'unit' => 'garrafa',  'required' => false, 'notes' => null],
                ['q' => 'rosa vermelha',  'purpose' => 'oferta',    'qty' => 7,  'unit' => 'un',       'required' => false, 'notes' => null],
                ['q' => 'vela vermelha',  'purpose' => 'oferta',    'qty' => 7,  'unit' => 'un',       'required' => false, 'notes' => null],
            ],
        ];

        foreach ($templates as $lineSlug => $items) {
            $lId = $lineId($lineSlug);
            if (!$lId) {
                $this->command?->warn("Linha '{$lineSlug}' não encontrada. Pulando templates dessa linha.");
                continue;
            }

            foreach ($items as $t) {
                $itemId = $findItemId($t['q']);
                if (!$itemId) {
                    $this->command?->warn("Item aproximado '{$t['q']}' não encontrado para '{$lineSlug}'. Pulando.");
                    continue;
                }

                DB::table('line_item_templates')->updateOrInsert(
                    [
                        'line_id'  => $lId,
                        'item_id'  => $itemId,
                        'purpose'  => $t['purpose'],
                    ],
                    [
                        'suggested_qty' => $t['qty'],
                        'unit'          => $t['unit'],
                        'required'      => (bool)($t['required'] ?? false),
                        'notes'         => $t['notes'] ?? null,
                        'updated_at'    => $now,
                        'created_at'    => $now,
                    ]
                );
            }
        }

        $this->command?->info('LineItemTemplatesSeeder finalizado.');
    }
}
