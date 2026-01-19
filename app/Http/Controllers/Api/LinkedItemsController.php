<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentItem;
use App\Models\Event;
use App\Models\EventPending;
use App\Models\InventoryItem;
use App\Models\SpiritualLine;
use Illuminate\Http\Request;

class LinkedItemsController extends Controller
{
    public function index(Request $request)
    {
        $eventId = $request->query('event_id');
        $lineId = $request->query('line_id');
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));

        $result = [];

        if ($eventId) {
            $event = Event::findOrFail($eventId);

            $result['event'] = $event->only(['id', 'title', 'status', 'start_date', 'end_date']);
            $result['inventory_items'] = InventoryItem::query()
                ->where('event_id', $eventId)
                ->orderByDesc('id')
                ->paginate($perPage, ['*'], 'inventory_page');

            $result['event_items'] = $event->eventItems()
                ->with('inventoryItem')
                ->orderByDesc('id')
                ->paginate($perPage, ['*'], 'event_items_page');

            $pendingsQuery = EventPending::query()->where('event_id', $eventId);
            $user = $request->user();
            $pendingsQuery->where(function ($where) use ($user) {
                $where->where('is_private', false);
                if ($user) {
                    $where->orWhere(function ($private) use ($user) {
                        $private->where('is_private', true)
                            ->where(function ($visible) use ($user) {
                                $visible->where('created_by', $user->id)
                                    ->orWhere('assignee_id', $user->id);
                            });
                    });
                }
            });

            $result['pendings'] = $pendingsQuery
                ->orderByDesc('id')
                ->paginate($perPage, ['*'], 'pendings_page');
        }

        if ($lineId) {
            $line = SpiritualLine::findOrFail($lineId);

            $result['line'] = $line->only(['id', 'name', 'slug', 'type', 'status']);
            $result['inventory_items_by_line'] = InventoryItem::query()
                ->whereHas('lines', function ($q) use ($lineId) {
                    $q->where('spiritual_lines.id', $lineId);
                })
                ->orderByDesc('id')
                ->paginate($perPage, ['*'], 'inventory_line_page');

            $result['content_items_by_line'] = ContentItem::query()
                ->whereHas('lines', function ($q) use ($lineId) {
                    $q->where('spiritual_lines.id', $lineId);
                })
                ->orderByDesc('id')
                ->paginate($perPage, ['*'], 'content_line_page');
        }

        if (!$eventId && !$lineId) {
            return response()->json(['message' => 'Provide event_id or line_id'], 422);
        }

        return response()->json($result);
    }
}
