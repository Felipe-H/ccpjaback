<?php

namespace App\Services;

use App\Models\Guide;

class GuideResolver
{
    /**
     * @param array<int> $lineIdsResolved
     * @param bool $includeInactive
     * @return array<int, array{id:int,name:string,nickname:?string,line_id:int,active:bool}>
     */
    public function listByLines(array $lineIdsResolved, bool $includeInactive = false): array
    {
        if (empty($lineIdsResolved)) return [];

        $q = Guide::query()
            ->whereIn('line_id', $lineIdsResolved)
            ->select(['id','name','nickname','line_id','active']);

        if (!$includeInactive) $q->where('active', true);

        return $q->get()->map(fn($g) => [
            'id' => $g->id,
            'name' => $g->name,
            'nickname' => $g->nickname,
            'line_id' => $g->line_id,
            'active' => (bool)$g->active,
        ])->values()->all();
    }
}
