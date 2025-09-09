<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialRecord;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinancialRecordController extends Controller
{
    public function index(Request $req)
    {
        $q = FinancialRecord::query();

        // Filtros opcionais
        if ($month = $req->query('month')) { // yyyy-mm
            $q->where('date', 'like', "$month%");
        }
        if ($type = $req->query('type')) {
            $q->where('type', $type); // income | expense
        }
        if ($status = $req->query('status')) {
            $q->where('payment_status', $status); // paid | pending | estimated
        }
        if ($category = $req->query('category')) {
            $q->where('category', $category);
        }
        if ($itemId = $req->query('item_id')) {
            $q->where('item_id', $itemId);
        }
        if ($eventId = $req->query('event_id')) {
            $q->where('event_id', $eventId);
        }
        if ($purchaseOnly = $req->boolean('purchase_only', false)) {
            $q->whereNotNull('purchase_id');
        }

        return $q->orderBy('date','desc')->orderBy('id','desc')->get();
    }

    public function store(Request $req)
    {
        $data = $req->validate([
            'type'           => ['required', Rule::in(['income','expense'])],
            'category'       => 'required|string|max:255',
            'description'    => 'required|string|max:255',
            'amount'         => 'nullable|numeric|min:0',       // pode ser 0 quando "estimated"
            'amount_estimated' => 'nullable|numeric|min:0',
            'date'           => 'nullable|date',
            'payment_status' => ['required', Rule::in(['paid','pending','estimated'])],
            'item_id'        => 'nullable|exists:inventory_items,id',
            'event_id'       => 'nullable|integer',             // FK futura
            'purchase_id'    => 'nullable|exists:purchase_batches,id',
            'meta'           => 'nullable|array',
        ]);

        $data['user_id'] = $req->user()->id ?? null;

        // Se for "estimated" e amount vier vazio, tente usar amount_estimated
        if (($data['payment_status'] ?? null) === 'estimated' && empty($data['amount']) && !empty($data['amount_estimated'])) {
            $data['amount'] = 0;
        }

        return FinancialRecord::create($data);
    }

    public function update(Request $req, FinancialRecord $finance): ?FinancialRecord
    {
        $data = $req->validate([
            'type'           => ['sometimes','required', Rule::in(['income','expense'])],
            'category'       => 'sometimes|required|string|max:255',
            'description'    => 'sometimes|required|string|max:255',
            'amount'         => 'sometimes|nullable|numeric|min:0',
            'amount_estimated' => 'sometimes|nullable|numeric|min:0',
            'date'           => 'nullable|date',
            'payment_status' => ['sometimes','required', Rule::in(['paid','pending','estimated'])],
            'item_id'        => 'sometimes|nullable|exists:inventory_items,id',
            'event_id'       => 'sometimes|nullable|integer',
            'purchase_id'    => 'sometimes|nullable|exists:purchase_batches,id',
            'meta'           => 'sometimes|nullable|array',
        ]);

        $finance->update($data);
        return $finance->fresh();
    }

    public function destroy(FinancialRecord $finance)
    {
        $finance->delete();
        return response()->noContent();
    }
}
