<?php

namespace App\Http\Controllers\Api;

use App\Actions\Events\FinalizeEventAction;
use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventsController extends Controller
{
    public function __construct(private FinalizeEventAction $finalizeEvent)
    {
    }

    public function index(Request $req)
    {
        $this->finalizeDueEvents();

        $q = Event::query();

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

        return $q->orderByDesc('id')->paginate(20);
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

        return Event::create($data);
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

        return $event->fresh();
    }

    public function destroy(Event $event)
    {
        if ($event->status === 'done') {
            return response()->json(['message' => 'Evento finalizado n?o pode ser exclu?do.'], 422);
        }
        $event->delete();
        return response()->noContent();
    }

    public function confirm(Event $event)
    {
        if ($event->status === 'canceled') {
            return response()->json(['message' => 'Evento cancelado n?o pode ser confirmado.'], 422);
        }
        $event->update(['status' => 'confirmed']);
        return $event->fresh();
    }

    public function cancel(Event $event)
    {
        if ($event->status === 'done') {
            return response()->json(['message' => 'Evento finalizado n?o pode ser cancelado.'], 422);
        }
        $event->update(['status' => 'canceled']);
        return $event->fresh();
    }

    public function finalize(Event $event)
    {
        if ($event->status === 'done') {
            return response()->json(['message' => 'Evento j? finalizado.'], 422);
        }

        return $this->finalizeEvent->handle($event);
    }

    private function finalizeDueEvents(): void
    {
        Event::query()
            ->whereIn('status', ['planned','confirmed'])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', now()->toDateString())
            ->chunkById(50, function ($events) {
                foreach ($events as $event) {
                    $this->finalizeEvent->handle($event);
                }
            });
    }
}
