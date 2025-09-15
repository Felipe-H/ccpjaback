<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentItem;
use App\Models\SpiritualLine;
use Illuminate\Http\Request;

class ContentLinksController extends Controller
{
    public function batch(Request $req)
    {
        $data = $req->validate([
            'ids' => ['required','array'],
            'ids.*' => ['integer'],
        ]);

        $ids = array_values(array_unique($data['ids']));
        if (empty($ids)) {
            return response()->json(['data' => new \stdClass()]);
        }

        $items = ContentItem::with(['lines:id,name,type,parent_id'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $out = [];
        foreach ($ids as $id) {
            $item = $items->get($id);
            if (!$item) {
                $out[(string)$id] = [];
                continue;
            }
            $out[(string)$id] = $item->lines->map(function ($l) {
                return [
                    'id' => $l->id,
                    'name' => $l->name,
                    'type' => $l->type,
                    'parent_id' => $l->parent_id,
                    'path' => $this->buildPath($l),
                ];
            })->values();
        }

        return response()->json(['data' => $out]);
    }

    private function buildPath(SpiritualLine $line): array
    {
        $out = [];
        $current = $line;
        $guard = 0;
        while ($current && $current->parent_id && $guard < 32) {
            $parent = SpiritualLine::find($current->parent_id);
            if (!$parent) break;
            array_unshift($out, ['id' => $parent->id, 'name' => $parent->name, 'type' => $parent->type]);
            $current = $parent;
            $guard++;
        }
        return $out;
    }
}
