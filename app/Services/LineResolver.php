<?php

namespace App\Services;

use App\Models\SpiritualLine;

class LineResolver
{
    /**
     * @param array<int> $lineIds
     * @param bool $includeInactive
     * @return array{selected: array<int>, resolved: array<int, array{id:int,name:string,type:string,parent_id:?int,source:string}>}
     */
    public function resolve(array $lineIds, bool $includeInactive = false): array
    {
        $selected = SpiritualLine::query()
            ->whereIn('id', $lineIds)
            ->when(!$includeInactive, fn($q) => $q->where('status', 'ativo'))
            ->get(['id','name','type','parent_id']);

        // mapa [id => data]
        $resolved = [];
        foreach ($selected as $ln) {
            $resolved[$ln->id] = [
                'id' => $ln->id,
                'name' => $ln->name,
                'type' => $ln->type,
                'parent_id' => $ln->parent_id,
                'source' => 'selected',
            ];
        }

        if ($selected->isEmpty()) {
            return ['selected' => array_values($lineIds), 'resolved' => $resolved];
        }

        $selectedByType = [
            'orixa' => $selected->where('type','orixa')->pluck('id')->all(),
            'linha' => $selected->where('type','linha')->pluck('id')->all(),
            'falange' => $selected->where('type','falange')->pluck('id')->all(),
        ];

        // Selec. Orixá ⇒ incluir linhas filhas
        if (!empty($selectedByType['orixa'])) {
            $linhas = SpiritualLine::query()
                ->whereIn('parent_id', $selectedByType['orixa'])
                ->when(!$includeInactive, fn($q) => $q->where('status', 'ativo'))
                ->where('type','linha')
                ->get(['id','name','type','parent_id']);

            foreach ($linhas as $ln) {
                if (!isset($resolved[$ln->id])) {
                    $resolved[$ln->id] = [
                        'id' => $ln->id, 'name' => $ln->name, 'type' => $ln->type,
                        'parent_id' => $ln->parent_id, 'source' => 'descendant'
                    ];
                }
            }

            // Falanges das linhas incluídas
            $linhaIds = $linhas->pluck('id')->all();
            if (!empty($linhaIds)) {
                $falanges = SpiritualLine::query()
                    ->whereIn('parent_id', $linhaIds)
                    ->when(!$includeInactive, fn($q) => $q->where('status', 'ativo'))
                    ->where('type','falange')
                    ->get(['id','name','type','parent_id']);
                foreach ($falanges as $ln) {
                    if (!isset($resolved[$ln->id])) {
                        $resolved[$ln->id] = [
                            'id' => $ln->id, 'name' => $ln->name, 'type' => $ln->type,
                            'parent_id' => $ln->parent_id, 'source' => 'descendant'
                        ];
                    }
                }
            }
        }

        // Selec. Linha ⇒ incluir falanges filhas
        if (!empty($selectedByType['linha'])) {
            $falanges = SpiritualLine::query()
                ->whereIn('parent_id', $selectedByType['linha'])
                ->when(!$includeInactive, fn($q) => $q->where('status', 'ativo'))
                ->where('type','falange')
                ->get(['id','name','type','parent_id']);
            foreach ($falanges as $ln) {
                if (!isset($resolved[$ln->id])) {
                    $resolved[$ln->id] = [
                        'id' => $ln->id, 'name' => $ln->name, 'type' => $ln->type,
                        'parent_id' => $ln->parent_id, 'source' => 'descendant'
                    ];
                }
            }
        }

        return [
            'selected' => $selected->pluck('id')->values()->all(),
            'resolved' => $resolved,
        ];
    }
}
