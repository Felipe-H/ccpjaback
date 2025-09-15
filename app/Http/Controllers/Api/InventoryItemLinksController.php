<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateItemLinksRequest;
use App\Models\Guide;
use App\Models\GuideItem;
use App\Models\InventoryItem;
use App\Models\LineItemTemplate;
use App\Models\SpiritualLine;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class InventoryItemLinksController extends Controller
{
    public function update(UpdateItemLinksRequest $request, InventoryItem $item)
    {
        $merge      = $request->wantsMerge();          // true = mescla, false = substitui
        $cascadeUp  = $request->cascadeUp();           // se true, propaga falange -> linha -> orixá
        $defaults   = $request->defaults();

        $linesIn    = $request->lines();               // [{ line_id, default_qty?, unit?, required?, purpose?, notes? }, ...]
        $guidesIn   = $request->guides();              // [{ guide_id, default_qty?, unit?, required?, purpose?, notes? }, ...]

        // Normaliza helpers
        $normLine = function(array $row) use ($defaults) {
            return [
                'line_id'      => (int)$row['line_id'],
                'default_qty'  => Arr::get($row, 'default_qty', Arr::get($defaults,'default_qty', 1)),
                'unit'         => Arr::get($row, 'unit',        Arr::get($defaults,'unit')),
                'required'     => (bool)Arr::get($row, 'required', Arr::get($defaults,'required', false)),
                'purpose'      => Arr::get($row, 'purpose',     Arr::get($defaults,'purpose')),
                'notes'        => Arr::get($row, 'notes',       Arr::get($defaults,'notes')),
            ];
        };
        $normGuide = function(array $row) use ($defaults) {
            return [
                'guide_id'     => (int)$row['guide_id'],
                'default_qty'  => Arr::get($row, 'default_qty', Arr::get($defaults,'default_qty', 1)),
                'unit'         => Arr::get($row, 'unit',        Arr::get($defaults,'unit')),
                'required'     => (bool)Arr::get($row, 'required', Arr::get($defaults,'required', false)),
                'purpose'      => Arr::get($row, 'purpose',     Arr::get($defaults,'purpose')),
                'notes'        => Arr::get($row, 'notes',       Arr::get($defaults,'notes')),
            ];
        };

        $lines  = array_map($normLine,  $linesIn);
        $guides = array_map($normGuide, $guidesIn);

        // 1) Para cada guia enviado, garantimos o espelho em line_item_templates no nível da FALANGE do guia
        if (!empty($guides)) {
            $gids = array_map(fn($g) => $g['guide_id'], $guides);
            /** @var \Illuminate\Support\Collection<int,\App\Models\Guide> $grows */
            $grows = Guide::query()->whereIn('id', $gids)->get(['id','line_id']);
            $byId  = $grows->keyBy('id');

            foreach ($guides as $g) {
                $lineId = (int)optional($byId->get($g['guide_id']))->line_id;
                if ($lineId > 0) {
                    // só adiciona se ainda não estiver na lista de lines
                    $already = collect($lines)->contains(fn($l) => (int)$l['line_id'] === $lineId);
                    if (!$already) {
                        $lines[] = $normLine(['line_id' => $lineId] + $g); // herda defaults do guia
                    }
                }
            }
        }

        // 2) Cascade up (falange -> pais)
        if ($cascadeUp && !empty($lines)) {
            $lineIds = array_values(array_unique(array_map(fn($l) => (int)$l['line_id'], $lines)));

            // Carrega árvore incrementalmente para achar ancestrais
            $toVisit = $lineIds;
            $seen    = [];
            $parents = [];

            while (!empty($toVisit)) {
                $rows = SpiritualLine::query()
                    ->whereIn('id', $toVisit)
                    ->get(['id','parent_id','type']);

                $toVisit = [];
                foreach ($rows as $r) {
                    $seen[$r->id] = true;
                    if ($r->parent_id && !isset($seen[$r->parent_id])) {
                        $parents[]  = (int)$r->parent_id;
                        $toVisit[]  = (int)$r->parent_id;
                    }
                }
            }

            $parents = array_values(array_unique($parents));
            foreach ($parents as $pid) {
                $already = collect($lines)->contains(fn($l) => (int)$l['line_id'] === $pid);
                if (!$already) {
                    $lines[] = $normLine(['line_id' => $pid] + $defaults);
                }
            }
        }

        // 3) Persiste tudo (transação)
        DB::transaction(function () use ($item, $lines, $guides, $merge) {
            // --- line_item_templates ---
            $keepLineIds = [];
            foreach ($lines as $row) {
                $payload = [
                    'item_id'      => $item->id,
                    'line_id'      => (int)$row['line_id'],
                    'default_qty'  => $row['default_qty'],
                    'unit'         => $row['unit'],
                    'required'     => (bool)$row['required'],
                    'purpose'      => $row['purpose'],
                    'notes'        => $row['notes'],
                ];
                $tpl = LineItemTemplate::query()
                    ->where('item_id', $item->id)
                    ->where('line_id', (int)$row['line_id'])
                    ->first();

                if ($tpl) {
                    $tpl->update($payload);
                } else {
                    $tpl = LineItemTemplate::create($payload);
                }
                $keepLineIds[] = (int)$tpl->line_id;
            }

            if (!$merge) {
                LineItemTemplate::query()
                    ->where('item_id', $item->id)
                    ->when(!empty($keepLineIds), fn($q) => $q->whereNotIn('line_id', $keepLineIds))
                    ->delete();
            }

            // --- guide_items ---
            $keepGuideIds = [];
            foreach ($guides as $row) {
                $payload = [
                    'item_id'      => $item->id,
                    'guide_id'     => (int)$row['guide_id'],
                    'default_qty'  => $row['default_qty'],
                    'unit'         => $row['unit'],
                    'required'     => (bool)$row['required'],
                    'purpose'      => $row['purpose'],
                    'notes'        => $row['notes'],
                ];
                $gi = GuideItem::query()
                    ->where('item_id', $item->id)
                    ->where('guide_id', (int)$row['guide_id'])
                    ->first();

                if ($gi) {
                    $gi->update($payload);
                } else {
                    $gi = GuideItem::create($payload);
                }
                $keepGuideIds[] = (int)$gi->guide_id;
            }

            if (!$merge) {
                GuideItem::query()
                    ->where('item_id', $item->id)
                    ->when(!empty($keepGuideIds), fn($q) => $q->whereNotIn('guide_id', $keepGuideIds))
                    ->delete();
            }
        });

        // 4) Retorna visão consolidada atual
        $fresh = [
            'item'   => ['id'=>$item->id, 'name'=>$item->name],
            'lines'  => LineItemTemplate::query()->where('item_id', $item->id)->get(),
            'guides' => GuideItem::query()->where('item_id', $item->id)->get(),
            'meta'   => [
                'merge'       => $merge,
                'cascade_up'  => $cascadeUp,
            ]
        ];

        return response()->json($fresh);
    }
}
