<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\LineItemTemplate;
use App\Models\GuideItem;
use App\Models\SpiritualLine;

class SuggestionEngine
{
    /**
     * @param int[] $resolvedLineIds
     * @param array $guides
     * @param bool  $onlyRequired
     */
    public function build(array $resolvedLineIds, array $guides, bool $onlyRequired = false): array
    {
        $linesMap = SpiritualLine::query()
            ->whereIn('id', $resolvedLineIds)
            ->get(['id','name','type','parent_id'])
            ->keyBy('id');

        $guideIds   = array_map(fn($g) => (int)($g['id'] ?? $g->id), $guides);
        $guidesMap  = collect($guides)->keyBy(fn($g) => (int)($g['id'] ?? $g->id));


        $lineTpls = LineItemTemplate::query()
            ->whereIn('line_id', $resolvedLineIds)
            ->get(['line_id','item_id','purpose','suggested_qty','unit','required','notes']);

        $guideLinks = GuideItem::query()
            ->whereIn('guide_id', $guideIds)
            ->get(['guide_id','item_id','purpose','default_qty','unit','required','notes']);

        $itemIds = array_values(array_unique(array_merge(
            $lineTpls->pluck('item_id')->all(),
            $guideLinks->pluck('item_id')->all()
        )));

        $invMap = InventoryItem::query()
            ->whereIn('id', $itemIds)
            ->get(['id','name','category','priority','purchase_type','quantity','ideal_quantity'])
            ->keyBy('id');

        $out = [];
        $addLine = function (&$rec, $tpl) use ($linesMap) {
            $lid = (int)$tpl->line_id;
            if (!isset($rec['sources']['lines_index'][$lid])) {
                $rec['sources']['lines'][] = [
                    'line_id'   => $lid,
                    'name'      => optional($linesMap->get($lid))->name,
                    'type'      => optional($linesMap->get($lid))->type,
                    'required'  => (bool)$tpl->required,
                    'unit'      => $tpl->unit,
                ];
                $rec['sources']['lines_index'][$lid] = true;
            }
        };
        $addGuide = function (&$rec, $gl) use ($guidesMap) {
            $gid = (int)$gl->guide_id;
            if (!isset($rec['sources']['guides_index'][$gid])) {
                $g  = $guidesMap->get($gid);
                $rec['sources']['guides'][] = [
                    'guide_id' => $gid,
                    'name'     => is_array($g) ? ($g['name'] ?? null) : ($g->name ?? null),
                    'line_id'  => is_array($g) ? ($g['line_id'] ?? null) : ($g->line_id ?? null),
                    'required' => (bool)$gl->required,
                    'unit'     => $gl->unit,
                ];
                $rec['sources']['guides_index'][$gid] = true;
            }
        };

        foreach ($lineTpls as $tpl) {
            $itemId = (int)$tpl->item_id;
            $inv    = $invMap->get($itemId);
            if (!$inv) continue;

            if (!isset($out[$itemId])) {
                $out[$itemId] = [
                    'item_id'         => $itemId,
                    'name'            => $inv->name,
                    'category'        => $inv->category,
                    'priority'        => $inv->priority,
                    'purchase_type'   => $inv->purchase_type,
                    'qty_in_stock'    => (int)$inv->quantity,
                    'unit'            => $tpl->unit ?? null,
                    'qty_suggested'   => 0,
                    'qty_to_buy'      => null,
                    'required'        => false,
                    'sources'         => [
                        'lines'        => [],
                        'guides'       => [],
                        'lines_index'  => [],
                        'guides_index' => [],
                    ],
                    'conflicts'       => [],
                    'notes_proposed'  => null,
                ];
            }
            $rec = &$out[$itemId];

            if (!$onlyRequired || $tpl->required) {
                $qty = (float)($tpl->suggested_qty ?? 1);
                $rec['qty_suggested'] += $qty;
                $rec['required'] = $rec['required'] || (bool)$tpl->required;
            }
            $addLine($rec, $tpl);
            unset($rec); // quebrar ref
        }

        foreach ($guideLinks as $gl) {
            $itemId = (int)$gl->item_id;
            $inv    = $invMap->get($itemId);
            if (!$inv) continue;

            if (!isset($out[$itemId])) {
                $out[$itemId] = [
                    'item_id'         => $itemId,
                    'name'            => $inv->name,
                    'category'        => $inv->category,
                    'priority'        => $inv->priority,
                    'purchase_type'   => $inv->purchase_type,
                    'qty_in_stock'    => (int)$inv->quantity,
                    'unit'            => $gl->unit ?? null,
                    'qty_suggested'   => 0,
                    'qty_to_buy'      => null,
                    'required'        => false,
                    'sources'         => [
                        'lines'        => [],
                        'guides'       => [],
                        'lines_index'  => [],
                        'guides_index' => [],
                    ],
                    'conflicts'       => [],
                    'notes_proposed'  => null,
                ];
            }
            $rec = &$out[$itemId];

            if (!$onlyRequired || $gl->required) {
                $qty = (float)($gl->default_qty ?? 1);
                $rec['qty_suggested'] += $qty;
                $rec['required'] = $rec['required'] || (bool)$gl->required;
            }
            $addGuide($rec, $gl);
            unset($rec);
        }

        foreach ($out as &$rec) {
            $ideal = $invMap[$rec['item_id']]->ideal_quantity ?? 0;
            $rec['qty_to_buy'] = ($rec['qty_suggested'] !== null && $ideal !== null)
                ? max(0, (int)$rec['qty_suggested'] - (int)$rec['qty_in_stock'])
                : null;


            $ln = array_map(fn($l) => $l['name'] ?? ('Linha #'.$l['line_id']), $rec['sources']['lines']);
            $gn = array_map(fn($g) => $g['name'] ?? ('Guia #'.$g['guide_id']), $rec['sources']['guides']);
            $parts = [];
            if (!empty($ln)) $parts[] = 'Linhas: '.implode(', ', $ln);
            if (!empty($gn)) $parts[] = 'Guias: '.implode(', ', $gn);
            $rec['notes_proposed'] = !empty($parts) ? implode(' | ', $parts) : null;

            unset($rec['sources']['lines_index'], $rec['sources']['guides_index']);
        }
        unset($rec);

        $suggestions = array_values($out);
        $kpis = [
            'items_suggested_count' => count($suggestions),
            'required_count'        => count(array_filter($suggestions, fn($r) => $r['required'])),
            'conflicts_count'       => 0,
            'total_qty_suggested'   => (float)array_sum(array_map(fn($r) => (float)($r['qty_suggested'] ?? 0), $suggestions)),
            'total_qty_to_buy'      => (float)array_sum(array_map(fn($r) => (float)($r['qty_to_buy'] ?? 0), $suggestions)),
        ];

        return [
            'suggestions' => $suggestions,
            'kpis'        => $kpis,
        ];
    }
}
