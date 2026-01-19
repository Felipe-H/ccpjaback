<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventItem;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EventsController extends Controller
{
    public function index(Request $req)
    {
        $cacheVersion = Cache::get('events_cache_version', 1);
        $cacheKey = 'events:index:v' . $cacheVersion . ':' . md5(json_encode($req->query()));
        $perPage = max(1, min(100, (int) $req->query('per_page', 20)));

        return Cache::remember($cacheKey, 60, function () use ($req, $perPage) {
            $q = Event::query()
                ->select([
                    'id',
                    'title',
                    'status',
                    'start_date',
                    'end_date',
                    'location',
                    'notes',
                    'created_by',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ]);

            if ($req->boolean('only_deleted')) {
                $q->onlyTrashed();
            } elseif ($req->boolean('include_deleted')) {
                $q->withTrashed();
            }

            if ($s = $req->query('q')) {
                $q->where(function ($w) use ($s) {
                    $w->where('title', 'like', "%{$s}%")
                        ->orWhere('location', 'like', "%{$s}%")
                        ->orWhere('notes', 'like', "%{$s}%");
                });
            }

            if ($status = $req->query('status')) {
                $q->whereIn('status', (array) $status);
            }

            if ($from = $req->query('date_from')) {
                $q->whereDate('start_date', '>=', $from);
            }
            if ($to = $req->query('date_to')) {
                $q->whereDate('end_date', '<=', $to);
            }

            return $q->orderByDesc('id')->paginate($perPage);
        });
    }

    public function store(Request $req)
    {
        $data = $req->validate([
            'title'      => 'required|string|max:255',
            'status'     => ['sometimes','in:planned,confirmed,done,canceled'],
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'location'   => 'nullable|string|max:255',
            'notes'      => 'nullable|string',
        ]);

        $data['created_by'] = $req->user()->id ?? null;
        $data['status']     = $data['status'] ?? 'planned';

        $event = Event::create($data);
        $this->bumpCacheVersion();
        return $event;
    }

    public function show($id)
    {
        $event = \App\Models\Event::with(['eventItems.inventoryItem','pendings'])
            ->findOrFail($id);

        $links = $event->eventItems;
        $total = $links->count();
        $ready = $links->where('is_ready', true)->count();
        $readiness = $total > 0 ? round(($ready / $total) * 100) : 0;

        $sumRequired = $links->sum('quantity_required');
        $sumUsed     = $links->sum('quantity_used');

        return [
            'event' => $event,
            'kpis' => [
                'readiness_percent' => $readiness,
                'total_required'    => (int)$sumRequired,
                'total_used'        => (int)$sumUsed,
                'remaining'         => max(0, (int)$sumRequired - (int)$sumUsed),
            ],
        ];
    }


    public function update(Request $req, Event $event)
    {
        $data = $req->validate([
            'title'      => 'sometimes|required|string|max:255',
            'status'     => ['sometimes','required', Rule::in(['planned','confirmed','done','canceled'])],
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'location'   => 'nullable|string|max:255',
            'notes'      => 'nullable|string',
        ]);

        $event->fill($data)->save();
        $this->bumpCacheVersion();

        return $event->fresh();
    }

    public function destroy(Event $event)
    {
        $event->delete();
        $this->bumpCacheVersion();
        return response()->noContent();
    }

    public function confirm(Event $event)
    {
        if ($event->status === 'canceled') {
            return response()->json(['message' => 'Evento cancelado não pode ser confirmado.'], 422);
        }
        $event->update(['status' => 'confirmed']);
        $this->bumpCacheVersion();
        return $event->fresh();
    }

    public function cancel(Event $event)
    {
        if ($event->status === 'done') {
            return response()->json(['message' => 'Evento finalizado não pode ser cancelado.'], 422);
        }
        $event->update(['status' => 'canceled']);
        $this->bumpCacheVersion();
        return $event->fresh();
    }

    public function finalize(Event $event)
    {
        if ($event->status === 'done') {
            return response()->json(['message' => 'Evento já finalizado.'], 422);
        }

        DB::transaction(function () use ($event) {
            $links = $event->eventItems()->with('inventoryItem')->lockForUpdate()->get();

            foreach ($links as $link) {
                $used = (int)$link->quantity_used;
                $req  = (int)$link->quantity_required;

                if ($used > $req) {
                    abort(422, "Consumo ($used) maior que requerido ($req) para o item ID {$link->inventory_item_id}.");
                }

                if ($link->is_from_stock) {
                    $item   = $link->inventoryItem;
                    $newQty = max(0, (int)$item->quantity - $used);

                    $item->quantity = $newQty;
                    $item->status   = $this->computeStatus($newQty, (int)$item->ideal_quantity);
                    $item->save();
                }
            }

            $event->update(['status' => 'done']);
        });

        $this->bumpCacheVersion();
        return $event->fresh(['eventItems.inventoryItem']);
    }

    private function computeStatus(int $quantity, int $idealQuantity): string
    {
        if ($quantity <= 0) return 'to_buy';
        if ($idealQuantity > 0 && $quantity < $idealQuantity) return 'low_stock';
        return 'available';
    }

    private function bumpCacheVersion(): void
    {
        Cache::increment('events_cache_version');
    }
}

