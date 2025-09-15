<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guide;
use Illuminate\Http\Request;

class GuidesController extends Controller
{
    public function index(Request $request)
    {
        $q = Guide::query()->with(['line:id,name']);

        if ($s = $request->query('q')) {
            $q->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")
                    ->orWhere('nickname', 'like', "%{$s}%");
            });
        }

        if ($request->filled('line_id')) {
            $q->where('line_id', (int)$request->query('line_id'));
        }

        if ($request->filled('active')) {
            $active = filter_var($request->query('active'), FILTER_VALIDATE_BOOL);
            $q->where('active', $active);
        }

        $limit = min(max((int)$request->query('limit', 10), 1), 50);

        $rows = $q->orderBy('name')
            ->limit($limit)
            ->get(['id','name','nickname','line_id','active']);

        return $rows->map(fn($g) => [
            'id'        => $g->id,
            'name'      => $g->name,
            'nickname'  => $g->nickname,
            'line_id'   => $g->line_id,
            'line_name' => optional($g->line)->name,
            'active'    => (bool)$g->active,
        ]);
    }
}
