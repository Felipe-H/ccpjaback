<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryItemController extends Controller
{
    public function index(Request $req)
    {
        $q = InventoryItem::query();

        // ----- Filtro de visibilidade (common | event | both) -----
        $scope   = $req->query('scope');      // common | event | both
        $eventId = $req->query('event_id');   // obrigatório quando scope=event ou scope=both

        if ($scope === 'event' && $eventId) {
            // apenas itens específicos do evento informado
            $q->where('scope', 'event')
                ->where('event_id', $eventId);
        } elseif ($scope === 'both' && $eventId) {
            // comuns + específicos do evento (OR lógico)
            $q->where(function ($w) use ($eventId) {
                $w->where('scope', 'common')
                    ->orWhere(function ($w2) use ($eventId) {
                        $w2->where('scope', 'event')->where('event_id', $eventId);
                    });
            });
        } else {
            // default seguro: só comuns
            $q->where('scope', 'common');
        }

        // ----- Demais filtros já existentes -----
        if ($s = $req->query('search')) {
            $q->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%")
                    ->orWhere('category', 'like', "%{$s}%");
            });
        }

        if ($cat = $req->query('category')) {
            $q->where('category', $cat);
        }

        if ($prio = $req->query('priority')) {
            $q->whereIn('priority', (array) $prio);
        }

        if ($req->boolean('to_buy')) {
            $q->whereColumn('quantity', '<', 'ideal_quantity');
        }

        $items = $q->orderByDesc('id')->get();

        $items->each(function ($item) {
            $item->qty_to_buy = max(($item->ideal_quantity ?? 0) - ($item->quantity ?? 0), 0);
        });

        return $items;
    }


    public function store(Request $req)
    {
        $data = $req->validate([
            'name'           => 'required|string|max:255',
            'quantity'       => 'required|integer|min:0',
            'ideal_quantity' => 'required|integer|min:0',
            'unit_price'     => 'required_without:price|nullable|numeric|min:0',
            'price'          => 'required_without:unit_price|nullable|numeric|min:0',
            'description'    => 'nullable|string',
            'category'       => 'required|string|max:255',
            'purchase_type'  => ['required', Rule::in(['donation', 'member', 'purchase'])],
            'priority'       => ['required', Rule::in(['low', 'medium', 'high'])],
            'date_added'     => 'nullable|date',
            'scope'    => ['nullable', Rule::in(['common','event'])],
            'event_id' => ['nullable','integer'],
        ]);

        $data['scope'] = $data['scope'] ?? 'common';
        if (($data['scope'] ?? 'common') !== 'event') {
            $data['event_id'] = null;
        }

        $data['price'] = $data['unit_price'] ?? $data['price'] ?? 0;
        unset($data['unit_price']);

        $data['user_id'] = $req->user()->id ?? null;

        $data['status'] = $this->computeStatus(
            (int)($data['quantity'] ?? 0),
            (int)($data['ideal_quantity'] ?? 0)
        );

        $item = InventoryItem::create($data);

        $item->qty_to_buy = max(($item->ideal_quantity ?? 0) - ($item->quantity ?? 0), 0);

        return $item;
    }

    public function update(Request $req, InventoryItem $item)
    {
        $data = $req->validate([
            'name'           => 'sometimes|required|string|max:255',
            'quantity'       => 'sometimes|required|integer|min:0',
            'ideal_quantity' => 'sometimes|required|integer|min:0',
            'unit_price'     => 'sometimes|nullable|numeric|min:0',
            'price'          => 'sometimes|nullable|numeric|min:0',
            'description'    => 'nullable|string',
            'category'       => 'sometimes|required|string|max:255',
            'purchase_type'  => ['sometimes','required', Rule::in(['donation','member','purchase'])],
            'priority'       => ['sometimes','required', Rule::in(['low','medium','high'])],
            'date_added'     => 'nullable|date',
            'scope'    => ['nullable', Rule::in(['common','event'])],
            'event_id' => ['nullable','integer'],
        ]);

        if (array_key_exists('unit_price', $data)) {
            $data['price'] = $data['unit_price'];
            unset($data['unit_price']);
        }

        $data['scope'] = $data['scope'] ?? 'common';
        if (($data['scope'] ?? 'common') !== 'event') {
            $data['event_id'] = null;
        }

        $item->fill($data);

        $quantity       = array_key_exists('quantity', $data) ? (int)$data['quantity'] : (int)$item->quantity;
        $idealQuantity  = array_key_exists('ideal_quantity', $data) ? (int)$data['ideal_quantity'] : (int)$item->ideal_quantity;

        $item->status = $this->computeStatus($quantity, $idealQuantity);

        $item->save();

        $fresh = $item->fresh();
        $fresh->qty_to_buy = max(($fresh->ideal_quantity ?? 0) - ($fresh->quantity ?? 0), 0);

        return $fresh;
    }

    public function destroy(InventoryItem $item)
    {
        $item->delete();
        return response()->noContent();
    }

    public function changeQuantity(Request $req, InventoryItem $item)
    {
        $data = $req->validate([
            'change' => 'nullable|integer',
            'delta'  => 'nullable|integer',
        ]);

        $delta = $data['change'] ?? $data['delta'] ?? 0;
        $newQty = max(0, (int)$item->quantity + (int)$delta);

        $newStatus = $this->computeStatus($newQty, (int)$item->ideal_quantity);

        $item->update([
            'quantity' => $newQty,
            'status'   => $newStatus,
        ]);

        $fresh = $item->fresh();
        $fresh->qty_to_buy = max(($fresh->ideal_quantity ?? 0) - ($fresh->quantity ?? 0), 0);

        return $fresh;
    }

    /**
     * Regras de status
     */
    private function computeStatus(int $quantity, int $idealQuantity): string
    {
        if ($quantity <= 0) {
            return 'to_buy';
        }

        if ($idealQuantity > 0 && $quantity < $idealQuantity) {
            return 'low_stock';
        }

        return 'available';
    }
}
