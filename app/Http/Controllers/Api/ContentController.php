<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentItem;
use App\Models\SpiritualLine;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContentController extends Controller
{
    public function index(Request $req)
    {
        $q = ContentItem::query()->with([
            'tags:id,name,slug',
            'lines:id,name,type,parent_id',
        ]);

        if ($s = trim((string)$req->query('q'))) {
            $q->where(function ($w) use ($s) {
                $w->where('title', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%")
                    ->orWhereHas('tags', function ($tw) use ($s) {
                        $tw->where('name', 'like', "%{$s}%")
                            ->orWhere('slug', 'like', "%{$s}%");
                    });
            });
        }

        $types = (array)$req->query('type', []);
        if (!empty($types)) {
            $q->whereIn('type', $types);
        }

        if ($status = $req->query('status')) {
            $q->where('status', $status);
        }

        if ($visibility = $req->query('visibility')) {
            $q->where('visibility', $visibility);
        }

        $tags = (array)$req->query('tag', []);
        if (!empty($tags)) {
            $tagSlugs = array_map(fn($t) => Str::slug((string)$t), $tags);
            $q->whereHas('tags', fn($tw) => $tw->whereIn('slug', $tagSlugs));
        }

        $lineIds = array_values(array_filter(array_map('intval', (array)$req->query('line_id', []))));
        $includeDesc = $req->boolean('include_descendants');
        if (!empty($lineIds)) {
            $ids = $includeDesc ? $this->expandWithDescendants($lineIds) : $lineIds;
            $q->whereHas('lines', fn($lw) => $lw->whereIn('spiritual_lines.id', $ids));
        }

        $orderBy = in_array($req->query('order_by'), ['created_at','title']) ? $req->query('order_by') : 'created_at';
        $orderDir = strtolower((string)$req->query('order_dir')) === 'asc' ? 'asc' : 'desc';
        $q->orderBy($orderBy, $orderDir);

        $perPage = max(1, min(100, (int)$req->query('per_page', 24)));
        $page = max(1, (int)$req->query('page', 1));
        $p = $q->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $p->items(),
            'meta' => [
                'page' => $p->currentPage(),
                'per_page' => $p->perPage(),
                'total' => $p->total(),
            ],
        ]);
    }

    public function show(ContentItem $content)
    {
        $content->load([
            'tags:id,name,slug',
            'assets:id,content_item_id,disk,storage_path,original_name,mime,size,variant,width,height,created_at',
            'lines:id,name,type,parent_id',
            'trails:id,title,slug',
        ]);

        $lines = $content->lines->map(function ($l) {
            return [
                'id' => $l->id,
                'name' => $l->name,
                'type' => $l->type,
                'parent_id' => $l->parent_id,
                'path' => $this->buildPath($l),
                'pivot' => [
                    'role' => $l->pivot->role ?? null,
                    'is_primary' => (bool)($l->pivot->is_primary ?? false),
                    'weight' => (int)($l->pivot->weight ?? 0),
                ],
            ];
        })->values();

        $out = $content->toArray();
        $out['lines_detailed'] = $lines;

        return response()->json($out);
    }

    public function store(Request $req)
    {
        $data = $req->validate([
            'type' => ['required', Rule::in(['audio','video','pdf','link','book','text','presentation','playlist'])],
            'status' => ['required', Rule::in(['draft','published'])],
            'visibility' => ['required', Rule::in(['private','members','public'])],
            'title' => ['required','string','max:255'],
            'slug' => ['nullable','string','max:255','unique:content_items,slug'],
            'description' => ['nullable','string'],
            'source_url' => ['nullable','string','max:2048'],
            'cover_url' => ['nullable','string','max:2048'],
            'duration_sec' => ['nullable','integer','min:0'],
            'tags' => ['nullable','array'],
            'tags.*' => ['string','max:120'],
            'line_ids' => ['nullable','array'],
            'line_ids.*' => ['integer','exists:spiritual_lines,id'],
        ]);

        return DB::transaction(function () use ($data, $req) {
            $slug = $data['slug'] ?? Str::slug($data['title']);
            if (ContentItem::where('slug', $slug)->exists()) {
                $slug = Str::slug($data['title'].'-'.Str::random(6));
            }

            $item = ContentItem::create([
                'type' => $data['type'],
                'status' => $data['status'],
                'visibility' => $data['visibility'],
                'title' => $data['title'],
                'slug' => $slug,
                'description' => $data['description'] ?? null,
                'source_url' => $data['source_url'] ?? null,
                'cover_url' => $data['cover_url'] ?? null,
                'duration_sec' => $data['duration_sec'] ?? null,
                'created_by' => $req->user()->id ?? null,
            ]);

            if (!empty($data['tags'])) {
                $tagIds = $this->upsertTags($data['tags']);
                $item->tags()->sync($tagIds);
            }

            if (!empty($data['line_ids'])) {
                $item->lines()->sync($data['line_ids']);
            }

            $item->load(['tags:id,name,slug','lines:id,name,type,parent_id']);
            return response()->json($item, 201);
        });
    }

    public function update(Request $req, ContentItem $content)
    {
        $data = $req->validate([
            'type' => ['sometimes', Rule::in(['audio','video','pdf','link','book','text','presentation','playlist'])],
            'status' => ['sometimes', Rule::in(['draft','published'])],
            'visibility' => ['sometimes', Rule::in(['private','members','public'])],
            'title' => ['sometimes','string','max:255'],
            'slug' => ['sometimes','nullable','string','max:255', Rule::unique('content_items','slug')->ignore($content->id)],
            'description' => ['sometimes','nullable','string'],
            'source_url' => ['sometimes','nullable','string','max:2048'],
            'cover_url' => ['sometimes','nullable','string','max:2048'],
            'duration_sec' => ['sometimes','nullable','integer','min:0'],
            'tags' => ['sometimes','array'],
            'tags.*' => ['string','max:120'],
            'line_ids' => ['sometimes','array'],
            'line_ids.*' => ['integer','exists:spiritual_lines,id'],
        ]);

        return DB::transaction(function () use ($data, $content) {
            if (array_key_exists('title', $data) && empty($data['slug'])) {
                $data['slug'] = Str::slug($data['title']);
            }

            $content->fill($data);
            $content->save();

            if (array_key_exists('tags', $data)) {
                $tagIds = $this->upsertTags($data['tags'] ?? []);
                $content->tags()->sync($tagIds);
            }

            if (array_key_exists('line_ids', $data)) {
                $content->lines()->sync($data['line_ids'] ?? []);
            }

            $content->load(['tags:id,name,slug','lines:id,name,type,parent_id']);
            return response()->json($content);
        });
    }

    public function destroy(ContentItem $content)
    {
        $inTrails = $content->trails()->select(['trails.id','title'])->get();
        if ($inTrails->count() > 0) {
            return response()->json([
                'ok' => false,
                'code' => 'IN_USE',
                'trails' => $inTrails,
            ], 409);
        }

        return DB::transaction(function () use ($content) {
            $content->tags()->detach();
            $content->lines()->detach();
            $content->delete();
            return response()->json(['ok' => true]);
        });
    }

    private function upsertTags(array $names): array
    {
        $ids = [];
        foreach ($names as $n) {
            $name = trim((string)$n);
            if ($name === '') continue;
            $slug = Str::slug($name);
            $tag = Tag::firstOrCreate(['slug' => $slug], ['name' => $name]);
            $ids[] = $tag->id;
        }
        return array_values(array_unique($ids));
    }

    private function expandWithDescendants(array $ids): array
    {
        $seen = [];
        $queue = array_values(array_unique(array_map('intval', $ids)));
        while (!empty($queue)) {
            $batch = $queue;
            $queue = [];
            $children = SpiritualLine::query()->whereIn('parent_id', $batch)->pluck('id')->all();
            foreach ($batch as $id) {
                if (!in_array($id, $seen, true)) $seen[] = $id;
            }
            foreach ($children as $cid) {
                if (!in_array($cid, $seen, true)) {
                    $seen[] = $cid;
                    $queue[] = $cid;
                }
            }
        }
        return $seen;
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
