<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentItem;
use App\Models\Trail;
use App\Models\TrailItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TrailsController extends Controller
{
    public function index(Request $req)
    {
        $q = Trail::query()->withCount('contents');

        if ($s = trim((string)$req->query('q'))) {
            $q->where(function ($w) use ($s) {
                $w->where('title', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%");
            });
        }

        if ($status = $req->query('status')) {
            $q->where('status', $status);
        }

        $orderBy = in_array($req->query('order_by'), ['created_at','title']) ? $req->query('order_by') : 'created_at';
        $orderDir = strtolower((string)$req->query('order_dir')) === 'asc' ? 'asc' : 'desc';
        $q->orderBy($orderBy, $orderDir);

        $perPage = max(1, min(100, (int)$req->query('per_page', 24)));
        $page = max(1, (int)$req->query('page', 1));
        $p = $q->paginate($perPage, ['*'], 'page', $page);

        $data = $p->getCollection()->map(function (Trail $t) {
            return [
                'id' => $t->id,
                'title' => $t->title,
                'slug' => $t->slug,
                'status' => $t->status,
                'description' => $t->description,
                'cover_url' => $t->cover_url,
                'items_count' => $t->contents_count,
                'created_at' => $t->created_at,
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'page' => $p->currentPage(),
                'per_page' => $p->perPage(),
                'total' => $p->total(),
            ],
        ]);
    }

    public function show(Trail $trail)
    {
        $trail->load(['contents' => function ($q) {
            $q->select('content_items.id','type','title','cover_url','duration_sec');
        }]);

        $items = $trail->items()->with(['content:id,title,type,cover_url,duration_sec'])
            ->orderBy('order')
            ->get()
            ->map(function (TrailItem $ti) {
                return [
                    'content_id' => $ti->content_item_id,
                    'order' => $ti->order,
                    'required' => (bool)$ti->required,
                    'note' => $ti->note,
                    'content' => [
                        'id' => $ti->content->id,
                        'type' => $ti->content->type,
                        'title' => $ti->content->title,
                        'cover_url' => $ti->content->cover_url,
                        'duration_sec' => $ti->content->duration_sec,
                    ],
                ];
            })->values();

        return response()->json([
            'id' => $trail->id,
            'title' => $trail->title,
            'slug' => $trail->slug,
            'status' => $trail->status,
            'description' => $trail->description,
            'cover_url' => $trail->cover_url,
            'items' => $items,
            'created_at' => $trail->created_at,
            'updated_at' => $trail->updated_at,
        ]);
    }

    public function store(Request $req)
    {
        $data = $req->validate([
            'title' => ['required','string','max:255'],
            'slug' => ['nullable','string','max:255','unique:trails,slug'],
            'status' => ['required', Rule::in(['draft','published'])],
            'description' => ['nullable','string'],
            'cover_url' => ['nullable','string','max:2048'],
        ]);

        $slug = $data['slug'] ?? Str::slug($data['title']);
        if (Trail::where('slug', $slug)->exists()) {
            $slug = Str::slug($data['title'].'-'.Str::random(6));
        }

        $trail = Trail::create([
            'title' => $data['title'],
            'slug' => $slug,
            'status' => $data['status'],
            'description' => $data['description'] ?? null,
            'cover_url' => $data['cover_url'] ?? null,
            'created_by' => $req->user()->id ?? null,
        ]);

        return response()->json(['id' => $trail->id, 'ok' => true], 201);
    }

    public function update(Request $req, Trail $trail)
    {
        $data = $req->validate([
            'title' => ['sometimes','string','max:255'],
            'slug' => ['sometimes','nullable','string','max:255', Rule::unique('trails','slug')->ignore($trail->id)],
            'status' => ['sometimes', Rule::in(['draft','published'])],
            'description' => ['sometimes','nullable','string'],
            'cover_url' => ['sometimes','nullable','string','max:2048'],
        ]);

        if (array_key_exists('title', $data) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['title']);
        }

        $trail->fill($data);
        $trail->save();

        return response()->json(['ok' => true]);
    }

    public function destroy(Trail $trail)
    {
        return DB::transaction(function () use ($trail) {
            $trail->items()->delete();
            $trail->delete();
            return response()->json(['ok' => true]);
        });
    }

    public function updateItems(Request $req, Trail $trail)
    {
        $data = $req->validate([
            'items' => ['required','array','min:1'],
            'items.*.content_id' => ['required','integer','exists:content_items,id'],
            'items.*.order' => ['nullable','integer','min:1'],
            'items.*.required' => ['nullable','boolean'],
            'items.*.note' => ['nullable','string','max:400'],
        ]);

        $rows = [];
        $seen = [];
        $i = 1;
        foreach ($data['items'] as $it) {
            $cid = (int)$it['content_id'];
            if (isset($seen[$cid])) continue;
            $seen[$cid] = true;
            $rows[] = [
                'trail_id' => $trail->id,
                'content_item_id' => $cid,
                'order' => isset($it['order']) ? (int)$it['order'] : $i,
                'required' => (bool)($it['required'] ?? false),
                'note' => $it['note'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $i++;
        }

        return DB::transaction(function () use ($trail, $rows) {
            TrailItem::where('trail_id', $trail->id)->delete();
            if (!empty($rows)) {
                usort($rows, fn($a, $b) => ($a['order'] <=> $b['order']) ?: ($a['content_item_id'] <=> $b['content_item_id']));
                TrailItem::insert($rows);
            }

            $items = $trail->items()->with(['content:id,title,type,cover_url,duration_sec'])
                ->orderBy('order')
                ->get()
                ->map(function (TrailItem $ti) {
                    return [
                        'content_id' => $ti->content_item_id,
                        'order' => $ti->order,
                        'required' => (bool)$ti->required,
                        'note' => $ti->note,
                        'content' => [
                            'id' => $ti->content->id,
                            'type' => $ti->content->type,
                            'title' => $ti->content->title,
                            'cover_url' => $ti->content->cover_url,
                            'duration_sec' => $ti->content->duration_sec,
                        ],
                    ];
                })->values();

            return response()->json(['ok' => true, 'items' => $items]);
        });
    }
}
