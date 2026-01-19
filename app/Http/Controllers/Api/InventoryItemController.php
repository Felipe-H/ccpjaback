<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryItemController extends Controller
{
    public function index(Request $req)
    {
        $cacheVersion = Cache::get('inventory_items_cache_version', 1);
        $cacheKey = 'inventory_items:index:v' . $cacheVersion . ':' . md5(json_encode($req->query()));
        $perPage = max(1, min(100, (int) $req->query('per_page', 50)));

        return Cache::remember($cacheKey, 60, function () use ($req, $perPage) {
            $q = InventoryItem::query()
                ->select([
                    'id',
                    'name',
                    'quantity',
                    'ideal_quantity',
                    'price',
                    'category',
                    'purchase_type',
                    'priority',
                    'status',
                    'description',
                    'date_added',
                    'scope',
                    'event_id',
                    'user_id',
                    'created_at',
                    'updated_at',
                ]);

            $scope   = $req->query('scope', 'all');
            $eventId = $req->query('event_id');

            if ($scope === 'common') {
                $q->where('scope', 'common');

            } elseif ($scope === 'event') {
                $q->where('scope', 'event');
                if ($eventId) {
                    $q->where('event_id', $eventId);
                }

            } else {
                $q->where(function ($w) use ($eventId) {
                    $w->where('scope', 'common')
                        ->orWhere(function ($w2) use ($eventId) {
                            $w2->where('scope', 'event');
                            if ($eventId) {
                                $w2->where('event_id', $eventId);
                            }
                        });
                });
            }

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

            $items = $q->orderByDesc('id')->paginate($perPage);

            $items->getCollection()->transform(function ($item) {
                $item->qty_to_buy = max(($item->ideal_quantity ?? 0) - ($item->quantity ?? 0), 0);
                return $item;
            });

            return $items;
        });
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
            'event_id' => [
                Rule::requiredIf(fn() => $req->input('scope') === 'event'),
                'integer'
            ],
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

        $this->bumpCacheVersion();
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
            'scope'          => ['sometimes', Rule::in(['common','event'])],
            'event_id'       => ['sometimes','nullable','integer'],
        ]);

        if (array_key_exists('unit_price', $data)) {
            $data['price'] = $data['unit_price'];
            unset($data['unit_price']);
        }

        if (array_key_exists('scope', $data)) {
            if ($data['scope'] !== 'event') {
                $data['event_id'] = null;
            } else {
                if (!array_key_exists('event_id', $data)) {
                    $data['event_id'] = $item->event_id;
                }
            }
        } else {
            unset($data['scope'], $data['event_id']);
        }

        $item->fill($data);

        // recalcula status com os valores "novos" ou atuais
        $quantity      = array_key_exists('quantity', $data) ? (int)$data['quantity'] : (int)$item->quantity;
        $idealQuantity = array_key_exists('ideal_quantity', $data) ? (int)$data['ideal_quantity'] : (int)$item->ideal_quantity;

        $item->status = $this->computeStatus($quantity, $idealQuantity);
        $item->save();

        $fresh = $item->fresh();
        $fresh->qty_to_buy = max(($fresh->ideal_quantity ?? 0) - ($fresh->quantity ?? 0), 0);

        $this->bumpCacheVersion();
        return $fresh;
    }


    public function destroy(InventoryItem $item): \Illuminate\Http\JsonResponse
    {
        try {
            DB::transaction(function () use ($item) {
                if (method_exists($item, 'lines'))  $item->lines()->detach();
                if (method_exists($item, 'guides')) $item->guides()->detach();

                $item->delete();
            });

            $this->bumpCacheVersion();
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'ok' => false,
                'message' => 'Falha ao deletar item',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
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

        $this->bumpCacheVersion();
        return $fresh;
    }

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

    private function bumpCacheVersion(): void
    {
        Cache::increment('inventory_items_cache_version');
    }
}
