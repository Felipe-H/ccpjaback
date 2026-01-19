<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventItem;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventInventoryController extends Controller
{
    public function view(Request $req, Event $event)
    {
        $q = trim((string) $req->query('q', ''));

        // Carrega tudo que precisamos de uma vez
        $event->load([
            'eventItems.inventoryItem',// vínculo + item de estoque
            'pendings' => function ($q) use ($req) {
                $user = $req->user();
                $q->where(function ($where) use ($user) {
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

                $q->select('id', 'event_id', 'title', 'description', 'status', 'assignee_id', 'created_by', 'is_private', 'created_at')
                    ->orderByDesc('id');
            },
        ]);

        $items = [];
        $sumRequired = 0;
        $sumUsed     = 0;
        $readyCount  = 0;

        foreach ($event->eventItems as $link) {
            $it = $link->inventoryItem;
            if (!$it) {
                continue;
            }

            // Filtro de busca
            if ($q !== '') {
                $hay = strtolower(trim(($it->name ?? '') . ' ' . ($it->description ?? '') . ' ' . ($it->category ?? '')));
                if (strpos($hay, strtolower($q)) === false) {
                    continue;
                }
            }

            $required    = (int) ($link->quantity_required ?? 0);
            $used        = (int) ($link->quantity_used ?? 0);
            $remaining   = max(0, $required - $used);
            $qty         = (int) ($it->quantity ?? 0);
            $ideal       = (int) ($it->ideal_quantity ?? 0);
            $qtyToBuy    = max(0, $ideal - $qty);
            $autoReady   = $qty >= $remaining;
            $isReady     = (bool) ($link->is_ready ?? false);
            $afterPlan   = max(0, $remaining - $qtyToBuy);
            $effective   = $isReady || $autoReady || $afterPlan === 0;

            $items[] = [
                'inventory_item_id'  => (int) $it->id,
                'event_item_id'      => (int) $link->id,
                'name'               => (string) $it->name,
                'description'        => $it->description,
                'category'           => $it->category,
                'priority'           => $it->priority,
                'scope'              => $it->scope ?? 'common',
                'origin_event_id'    => $it->event_id ?? null,

                'quantity'           => $qty,         // estoque atual
                'ideal_quantity'     => $ideal,
                'status'             => $it->status,
                'unit_price'         => (float) ($it->unit_price ?? $it->price ?? 0),

                'required'           => $required,    // do vínculo
                'used'               => $used,
                'remaining'          => $remaining,
                'needed_by'          => $link->needed_by ?? $event->start_date,
                'notes'              => $link->notes,

                'is_ready_manual'    => $isReady,
                'is_ready_auto'      => $autoReady,
                'is_ready_effective' => $effective,

                'qty_to_buy'         => $qtyToBuy,
            ];

            $sumRequired += $required;
            $sumUsed     += $used;
            if ($effective) $readyCount++;
        }

        $total = count($items);
        $readiness = $total > 0 ? (int) round(($readyCount / $total) * 100) : 0;

        return [
            'event' => [
                'id'         => $event->id,
                'title'      => $event->title,
                'status'     => $event->status,
                'start_date' => $event->start_date,
                'end_date'   => $event->end_date,
                'location'   => $event->location,
                'notes'      => $event->notes,
            ],
            'kpis' => [
                'items_total'       => $total,
                'total_required'    => (int) $sumRequired,
                'total_used'        => (int) $sumUsed,
                'total_remaining'   => max(0, (int) $sumRequired - (int) $sumUsed),
                'readiness_percent' => $readiness,
            ],
            'items'    => $items,
            'pendings' => $event->pendings->map(function ($p) {
                return [
                    'id'          => (int) $p->id,
                    'event_id'    => (int) $p->event_id,
                    'title'       => (string) $p->title,
                    'description' => $p->description,
                    'status'      => (string) $p->status,
                    'assignee_id' => $p->assignee_id ? (int) $p->assignee_id : null,
                    'created_by'  => $p->created_by ? (int) $p->created_by : null,
                    'is_private'  => (bool) $p->is_private,
                    'created_at'  => $p->created_at,
                ];
            })->values(),
        ];
    }

    public function sync(Request $req, Event $event)
    {
        $data = $req->validate([
            'items'                                => 'required|array|min:1',
            'items.*.inventory_item_id'            => 'required|integer|exists:inventory_items,id',
            'items.*.quantity_required'            => 'required|integer|min:0',
            'items.*.notes'                        => 'nullable|string',
            'items.*.adjust_ideal_by_required'     => 'sometimes|boolean',
        ]);

        DB::transaction(function () use ($data, $event) {
            foreach ($data['items'] as $row) {
                $invId   = (int) $row['inventory_item_id'];
                $reqQty  = (int) $row['quantity_required'];
                $notes   = $row['notes'] ?? null;
                $adjust  = (bool) ($row['adjust_ideal_by_required'] ?? false);

                $inv = InventoryItem::whereKey($invId)->lockForUpdate()->firstOrFail();

                EventItem::updateOrCreate(
                    ['event_id' => $event->id, 'inventory_item_id' => $invId],
                    [
                        'quantity_required' => $reqQty,
                        'notes'             => $notes,
                        'needed_by'         => $event->start_date,
                        'is_from_stock'     => true,
                    ]
                );

                if ($adjust && $reqQty > 0) {
                    $inv->ideal_quantity = (int) $inv->ideal_quantity + $reqQty;
                    $inv->save();
                }
            }
        });

        // Devolve a view já atualizada
        return $this->view($req, $event);
    }
}
