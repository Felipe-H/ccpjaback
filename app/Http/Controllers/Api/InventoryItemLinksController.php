<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\UpdateItemLinksRequest;
use App\Models\InventoryItem;
use App\Models\SpiritualLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class InventoryItemLinksController extends Controller
{
    public function update(UpdateItemLinksRequest $req, InventoryItem $item): JsonResponse
    {
        $merge      = $req->mergeMode();
        $cascadeUp  = $req->cascadeUp();
        $lineIds    = $req->lineIds();
        $guideIds   = $req->guideIds();

        try {
            DB::transaction(function () use ($item, $merge, $cascadeUp, $lineIds, $guideIds) {

                $finalLineIds = $lineIds;
                if ($cascadeUp && !empty($lineIds)) {
                    $parents = $this->collectAncestors($lineIds);
                    $finalLineIds = array_values(array_unique(array_merge($finalLineIds, $parents)));
                }


                $existingLineIds = $item->lines()->pluck('spiritual_lines.id')->all();
                $targetLineIds   = $merge
                    ? array_values(array_unique(array_merge($existingLineIds, $finalLineIds)))
                    : $finalLineIds;

                $item->lines()->sync($targetLineIds);

                if (method_exists($item, 'guides')) {
                    $existingGuideIds = $item->guides()->pluck('guides.id')->all();
                    $targetGuideIds   = $merge
                        ? array_values(array_unique(array_merge($existingGuideIds, $guideIds)))
                        : $guideIds;

                    $item->guides()->sync($targetGuideIds);
                }
            });

            $freshLines  = $item->lines()->select(['spiritual_lines.id','name','type','parent_id'])->get();
            $freshGuides = method_exists($item, 'guides') ? $item->guides()->select(['guides.id','name'])->get() : collect();

            return response()->json([
                'ok' => true,
                'item_id' => $item->id,
                'saved' => [
                    'lines_count'  => $freshLines->count(),
                    'guides_count' => $freshGuides->count(),
                ],
                'lines'  => $freshLines,
                'guides' => $freshGuides,
                'meta' => [
                    'merge' => $merge,
                    'cascade_up' => $cascadeUp,
                ],
            ]);
        } catch (\Throwable $e) {
            // Loga e devolve info útil no 500
            report($e);
            return response()->json([
                'message' => 'Failed to update item links',
                'error'   => $e->getMessage(),
                'trace'   => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * Retorna todos os ancestrais (linha/orixá) das ids informadas
     * @param int[] $ids
     * @return int[]
     */
    private function collectAncestors(array $ids): array
    {
        $out = [];
        $map = SpiritualLine::query()->whereIn('id', $ids)->get(['id','parent_id'])->keyBy('id');

        $queue = $ids;
        while (!empty($queue)) {
            $currentId = array_pop($queue);
            $line = $map[$currentId] ?? SpiritualLine::find($currentId);
            if (!$line) continue;

            if ($line->parent_id && !in_array($line->parent_id, $out, true)) {
                $out[] = (int) $line->parent_id;
                if (!isset($map[$line->parent_id])) {
                    $map[$line->parent_id] = SpiritualLine::find($line->parent_id);
                }
                $queue[] = (int) $line->parent_id;
            }
        }
        return $out;
    }


    public function show(InventoryItem $item): JsonResponse
    {
        $item->load(['lines' => function ($q) {
            $q->select('spiritual_lines.id','name','type','parent_id');
        }]);

        $linked = $item->lines->map(function ($l) {
            return [
                'id'        => (int) $l->id,
                'name'      => $l->name,
                'type'      => $l->type,
                'parent_id' => $l->parent_id ? (int) $l->parent_id : null,
                'pivot'     => [
                    'purpose'       => $l->pivot->purpose,
                    'suggested_qty' => $l->pivot->suggested_qty,
                    'unit'          => $l->pivot->unit,
                    'required'      => (bool) $l->pivot->required,
                ],
            ];
        });

        $linkedIds    = $item->lines->pluck('id')->all();
        $ancestorIds  = $this->collectAncestors($linkedIds);
        $allNeededIds = array_values(array_unique(array_merge($linkedIds, $ancestorIds)));

        $map = SpiritualLine::query()
            ->whereIn('id', $allNeededIds)
            ->get(['id','name','type','parent_id'])
            ->keyBy('id');

        $withPaths = $linked->map(function (array $row) use ($map) {
            $path = [];
            $id   = $row['id'];
            while ($id && isset($map[$id])) {
                $node = $map[$id];
                array_unshift($path, [
                    'id'   => (int) $node->id,
                    'name' => $node->name,
                    'type' => $node->type,
                ]);
                $id = $node->parent_id ? (int) $node->parent_id : null;
            }
            $row['path'] = $path;
            return $row;
        });

        $ancestors = SpiritualLine::query()
            ->whereIn('id', $ancestorIds)
            ->get(['id','name','type','parent_id']);

        return response()->json([
            'ok'        => true,
            'item_id'   => $item->id,
            'linked'    => $withPaths,
            'ancestors' => $ancestors,
            'counts'    => [
                'linked'    => count($linkedIds),
                'ancestors' => count($ancestorIds),
            ],
        ]);
    }

    public function batch(Request $req): JsonResponse
    {
        $ids = $req->input('ids', $req->json('ids', []));
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)$ids), fn($v) => $v > 0)));

        $validator = \Validator::make(['ids' => $ids], [
            'ids' => 'required|array|max:200',
            'ids.*' => 'integer|min:1',
        ]);
        if ($validator->fails()) {
            return response()->json(['ok' => false, 'message' => $validator->errors()->first()], 422);
        }
        if (empty($ids)) {
            return response()->json(['ok' => true, 'data' => []]);
        }

        $pivots = DB::table('line_item_templates')
            ->whereIn('item_id', $ids)
            ->get(['item_id', 'line_id', 'purpose', 'suggested_qty', 'unit', 'required']);

        if ($pivots->isEmpty()) {
            return response()->json(['ok' => true, 'data' => []]);
        }

        $lineIds = $pivots->pluck('line_id')->unique()->values()->all();
        $linesMap = SpiritualLine::query()
            ->whereIn('id', $lineIds)
            ->get(['id','name','type','parent_id'])
            ->keyBy('id');

        $queue = $lineIds;
        while (!empty($queue)) {
            $needParents = [];
            foreach ($queue as $lid) {
                $ln = $linesMap->get($lid);
                if ($ln && $ln->parent_id && !$linesMap->has($ln->parent_id)) {
                    $needParents[] = (int)$ln->parent_id;
                }
            }
            $needParents = array_values(array_unique($needParents));
            if (empty($needParents)) break;

            $parents = SpiritualLine::query()
                ->whereIn('id', $needParents)
                ->get(['id','name','type','parent_id'])
                ->keyBy('id');

            // mescla no mapa
            foreach ($parents as $k => $v) {
                $linesMap[$k] = $v;
            }
            $queue = $needParents;
        }

        $buildPath = function (int $lineId) use ($linesMap): array {
            $out = [];
            $current = $linesMap->get($lineId);
            if (!$current) return $out;

            $stack = [];
            while ($current) {
                $stack[] = [
                    'id'   => (int)$current->id,
                    'name' => $current->name,
                    'type' => $current->type,
                ];
                if (!$current->parent_id) break;
                $current = $linesMap->get($current->parent_id);
                if (!$current) break;
            }
            return array_reverse($stack);
        };

        $out = [];
        foreach ($pivots as $p) {
            $ln = $linesMap->get($p->line_id);
            if (!$ln) continue;

            $out[$p->item_id][] = [
                'id'        => (int)$ln->id,
                'name'      => $ln->name,
                'type'      => $ln->type,
                'parent_id' => $ln->parent_id ? (int)$ln->parent_id : null,
                'path'      => $buildPath((int)$ln->id),
                'pivot'     => [
                    'purpose'       => $p->purpose,
                    'suggested_qty' => $p->suggested_qty,
                    'unit'          => $p->unit,
                    'required'      => (bool)$p->required,
                ],
            ];
        }

        return response()->json([
            'ok'   => true,
            'data' => $out,
            'meta' => [
                'items' => count($ids),
                'links' => $pivots->count(),
            ],
        ]);
    }


}
