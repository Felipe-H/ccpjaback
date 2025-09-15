<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        $lineIds    = $req->lineIds();   // normalizados
        $guideIds   = $req->guideIds();  // normalizados (se estiver usando guides mesmo)

        try {
            DB::transaction(function () use ($item, $merge, $cascadeUp, $lineIds, $guideIds) {
                // 1) Resolver ancestrais (linha/orixá) se cascade_up = true
                $finalLineIds = $lineIds;
                if ($cascadeUp && !empty($lineIds)) {
                    $parents = $this->collectAncestors($lineIds);
                    $finalLineIds = array_values(array_unique(array_merge($finalLineIds, $parents)));
                }

                // 2) Se "merge" = true, agregamos com os já existentes, senão substituímos
                $existingLineIds = $item->lines()->pluck('spiritual_lines.id')->all();
                $targetLineIds   = $merge
                    ? array_values(array_unique(array_merge($existingLineIds, $finalLineIds)))
                    : $finalLineIds;

                // Persistir via belongsToMany pivot: line_item_templates (item_id, line_id)
                $item->lines()->sync($targetLineIds);

                // Guides (se você usa mesmo tabela "guides" + pivot "guide_items")
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
                // garante que teremos os próximos ancestrais carregados
                if (!isset($map[$line->parent_id])) {
                    $map[$line->parent_id] = SpiritualLine::find($line->parent_id);
                }
                $queue[] = (int) $line->parent_id;
            }
        }
        return $out;
    }
}
