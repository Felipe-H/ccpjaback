<?php

// app/Http/Controllers/Api/InventoryItemController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryItemController extends Controller
{
    public function index(Request $req): \Illuminate\Database\Eloquent\Collection
    {
        $q = InventoryItem::query();
        if ($s = $req->query('search')) {
            $q->where(function($w) use ($s) {
                $w->where('name','like',"%$s%")
                    ->orWhere('description','like',"%$s%")
                    ->orWhere('category','like',"%$s%");
            });
        }
        return $q->orderBy('id','desc')->get();
    }

    public function store(Request $req) {
        $data = $req->validate([
            'name' => 'required|string|max:255',
            'quantity' => 'required|integer|min:0',
            'price' => 'required|numeric|min:0',
            'unit_price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'category' => 'required|string|max:255',
            'purchase_type' => ['required', Rule::in(['donation','member','purchase'])],
            'status' => ['required', Rule::in(['available','low_stock','out_of_stock','to_buy'])],
            'date_added' => 'nullable|date',
        ]);
        $data['user_id'] = $req->user()->id ?? null;
        $data['price'] = $data['unit_price'];
        unset($data['unit_price']);
        return InventoryItem::create($data);
    }

    public function update(Request $req, InventoryItem $item): ?InventoryItem
    {
        $data = $req->validate([
            'name' => 'sometimes|required|string|max:255',
            'quantity' => 'sometimes|required|integer|min:0',
            'price' => 'sometimes|required|numeric|min:0',
            'description' => 'nullable|string',
            'category' => 'sometimes|required|string|max:255',
            'purchase_type' => ['sometimes','required', Rule::in(['donation','member','purchase'])],
            'status' => ['sometimes','required', Rule::in(['available','low_stock','out_of_stock','to_buy'])],
            'date_added' => 'nullable|date',
        ]);
        $item->update($data);
        return $item->fresh();
    }

    public function destroy(InventoryItem $item) {
        $item->delete();
        return response()->noContent();
    }

    public function changeQuantity(Request $req, InventoryItem $item) {
        $data = $req->validate(['delta' => 'required|integer']);
        $new = max(0, $item->quantity + $data['delta']);
        $status = $item->status;
        if ($new === 0) $status = 'out_of_stock';
        else if ($new <= 5) $status = 'low_stock';
        else $status = 'available';
        $item->update(['quantity' => $new, 'status' => $status]);
        return $item->fresh();
    }
}

