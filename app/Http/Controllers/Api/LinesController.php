<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SpiritualLine;

class LinesController extends Controller
{
    public function tree(Request $request)
    {
        $q = SpiritualLine::query();

        // filtro por status
        if ($request->has('active')) {
            $active = filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN);
            $q->where('status', $active ? 'ativo' : 'inativo');
        }

        // filtro por tipo
        if ($type = $request->query('type')) {
            $q->where('type', $type);
        }

        // busca por nome/slug
        if ($search = $request->query('q')) {
            $q->where(function ($where) use ($search) {
                $like = "%{$search}%";
                $where->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            });
        }

        $rows = $q->orderBy('sort_order')->orderBy('name')
            ->get(['id','name','slug','type','parent_id','icon','color_hex','status']);

        // KPIs
        $kpis = [
            'total'    => $rows->count(),
            'orixas'   => $rows->where('type', 'orixa')->count(),
            'linhas'   => $rows->where('type', 'linha')->count(),
            'falanges' => $rows->where('type', 'falange')->count(),
        ];

        // Se flat=1, devolve plano
        $flat = filter_var($request->query('flat'), FILTER_VALIDATE_BOOLEAN);
        if ($flat) {
            return response()->json([
                'items' => $rows->map(function ($r) {
                    return [
                        'id'        => $r->id,
                        'name'      => $r->name,
                        'slug'      => $r->slug,
                        'type'      => $r->type,
                        'status'    => $r->status,
                        'icon'      => $r->icon,
                        'color_hex' => $r->color_hex,
                        'parent_id' => $r->parent_id,
                    ];
                })->values(),
                'kpis' => $kpis,
                'meta' => ['active' => $request->query('active'), 'flat' => true],
            ]);
        }

        // Monta árvore
        $byParent = [];
        foreach ($rows as $r) {
            $pid = $r->parent_id ?: 0;
            $byParent[$pid][] = $r;
        }

        $build = function ($parentId) use (&$build, $byParent) {
            $out = [];
            foreach ($byParent[$parentId] ?? [] as $r) {
                $node = [
                    'id'        => $r->id,
                    'name'      => $r->name,
                    'slug'      => $r->slug,
                    'type'      => $r->type,
                    'status'    => $r->status,
                    'icon'      => $r->icon,
                    'color_hex' => $r->color_hex,
                    'parent_id' => $r->parent_id,
                ];
                $children = $build($r->id);
                if (!empty($children)) {
                    $node['children'] = $children;
                }
                $out[] = $node;
            }
            return $out;
        };

        $tree = $build(0);

        return response()->json([
            'items' => $tree,
            'kpis'  => $kpis,
            'meta'  => ['active' => $request->query('active'), 'flat' => false],
        ]);
    }
}
