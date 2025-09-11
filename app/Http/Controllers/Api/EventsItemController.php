<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventItem;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventsItemController extends Controller
{
    public function index(Request $req, Event $event)
    {
        $q = $event->eventItems()->with('inventoryItem');

        if (!is_null($r = $req->query('is_ready'))) {
            $q->where('is_ready', filter_var($r, FILTER_VALIDATE_BOOLEAN));
        }

        if ($req->boolean('only_from_stock')) {
            $q->where('is_from_stock', true);
        }

        if ($from = $req->query('needed_by_from')) {
            $q->whereDate('needed_by', '>=', $from);
        }
        if ($to = $req->query('needed_by_to')) {
            $q->whereDate('needed_by', '<=', $to);
        }

        return $q->orderBy('id')->paginate(50);
    }

    public function store(Request $req, Event $event)
    {
        $payload = $req->validate([
            'items'                         => 'nullable|array',
            'items.*.inventory_item_id'     => 'required|integer|exists:inventory_items,id',
            'items.*.quantity_required'     => 'nullable|integer|min:0',
            'items.*.quantity_used'         => 'nullable|integer|min:0',
            'items.*.needed_by'             => 'nullable|date',
            'items.*.notes'                 => 'nullable|string',
        ]);

        $items = $payload['items'] ?? [];

        DB::transaction(function () use ($event, $items) {
            foreach ($items as $i) {
                $item = InventoryItem::findOrFail($i['inventory_item_id']);

                $isFromStock = ($item->scope ?? 'common') === 'common';
                $req  = (int)($i['quantity_required'] ?? 0);
                $used = (int)($i['quantity_used'] ?? 0);
                if ($used > $req) abort(422, 'quantity_used não pode exceder quantity_required.');

                EventItem::updateOrCreate(
                    ['event_id' => $event->id, 'inventory_item_id' => $item->id],
                    [
                        'quantity_required' => $req,
                        'quantity_used'     => $used,
                        'is_from_stock'     => $isFromStock,
                        'needed_by'         => $i['needed_by'] ?? null,
                        'notes'             => $i['notes'] ?? null,
                    ]
                );
            }
        });

        return $event->fresh(['eventItems.inventoryItem'])->eventItems;
    }

    public function update(Request $req, Event $event, EventItem $eventItem)
    {
        if ((int)$eventItem->event_id !== (int)$event->id) {
            abort(404);
        }

        $data = $req->validate([
            'quantity_required' => 'sometimes|integer|min:0',
            'quantity_used'     => 'sometimes|integer|min:0',
            'is_ready'          => 'sometimes|boolean',
            'needed_by'         => 'nullable|date',
            'notes'             => 'nullable|string',
        ]);

        DB::transaction(function () use ($event, $eventItem, $data) {
            $applyDelta = $event->status === 'done'
                && $eventItem->is_from_stock
                && array_key_exists('quantity_used', $data);

            $delta = 0;
            if ($applyDelta) {
                $new = (int)$data['quantity_used'];
                $old = (int)$eventItem->quantity_used;
                if ($new < 0) abort(422, 'quantity_used inválido.');
                if (array_key_exists('quantity_required', $data) && $new > (int)$data['quantity_required']) {
                    abort(422, 'quantity_used não pode exceder quantity_required.');
                }
                $delta = $new - $old;
            }

            $eventItem->fill($data)->save();

            if ($applyDelta && $delta !== 0) {
                $item = InventoryItem::lockForUpdate()->findOrFail($eventItem->inventory_item_id);
                $newQty = max(0, (int)$item->quantity - $delta); // delta positivo consome; negativo repõe
                $item->quantity = $newQty;
                $item->status   = $this->computeStatus($newQty, (int)$item->ideal_quantity);
                $item->save();
            }
        });

        return $eventItem->fresh('inventoryItem');
    }

    public function destroy(Event $event, EventItem $eventItem)
    {
        if ((int)$eventItem->event_id !== (int)$event->id) abort(404);

        if ((int)$eventItem->quantity_used > 0 && $event->status !== 'canceled') {
            return response()->json(['message' => 'Vínculo com consumo não pode ser removido.'], 422);
        }

        $eventItem->delete();
        return response()->noContent();
    }

    private function computeStatus(int $quantity, int $idealQuantity): string
    {
        if ($quantity <= 0) return 'to_buy';
        if ($idealQuantity > 0 && $quantity < $idealQuantity) return 'low_stock';
        return 'available';
    }
}
