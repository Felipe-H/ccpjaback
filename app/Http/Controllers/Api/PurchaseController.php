<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\PurchaseBatch;
use App\Models\PurchaseItem;
use App\Models\FinancialRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchaseController extends Controller
{
    public function index(Request $req)
    {
        $q = PurchaseBatch::with('items');

        if ($month = $req->query('month')) { // yyyy-mm
            $q->where('date', 'like', "$month%");
        }
        if ($status = $req->query('status')) {
            $q->where('payment_status', $status); // paid|pending
        }
        if ($eventId = $req->query('event_id')) {
            $q->where('event_id', $eventId);
        }

        return $q->orderBy('date','desc')->orderBy('id','desc')->get();
    }

    public function store(Request $req)
    {
        $data = $req->validate([
            'date'           => 'nullable|date',
            'event_id'       => 'nullable|integer',
            'payment_status' => ['required', Rule::in(['paid','pending'])],
            'notes'          => 'nullable|string',
            'items'          => 'required|array|min:1',
            'items.*.item_id'            => 'required|exists:inventory_items,id',
            'items.*.qty'                => 'required|numeric|min:0.001',
            'items.*.unit_price_real'    => 'required|numeric|min:0',
            'items.*.unit_price_estimated' => 'nullable|numeric|min:0',
        ]);

        $userId = $req->user()->id ?? null;

        return DB::transaction(function () use ($data, $userId) {
            $batch = PurchaseBatch::create([
                'date'           => $data['date'] ?? now()->toDateString(),
                'event_id'       => $data['event_id'] ?? null,
                'payment_status' => $data['payment_status'],
                'notes'          => $data['notes'] ?? null,
                'total_estimated'=> 0,
                'total_real'     => 0,
                'created_by'     => $userId,
            ]);

            $totalEstimated = 0;
            $totalReal = 0;

            foreach ($data['items'] as $row) {
                $subEst = isset($row['unit_price_estimated'])
                    ? $row['qty'] * $row['unit_price_estimated']
                    : null;

                $subReal = $row['qty'] * $row['unit_price_real'];

                $pi = PurchaseItem::create([
                    'purchase_id'          => $batch->id,
                    'item_id'              => $row['item_id'],
                    'qty'                  => $row['qty'],
                    'unit_price_estimated' => $row['unit_price_estimated'] ?? null,
                    'unit_price_real'      => $row['unit_price_real'],
                    'subtotal_estimated'   => $subEst,
                    'subtotal_real'        => $subReal,
                ]);

                // Atualiza estoque (incrementa)
                InventoryItem::where('id', $row['item_id'])->increment('quantity', $row['qty']);

                $totalReal += $subReal;
                if ($subEst !== null) $totalEstimated += $subEst;
            }

            $batch->update([
                'total_estimated' => $totalEstimated ?: null,
                'total_real'      => $totalReal,
            ]);

            $customDesc = trim((string)($data['notes'] ?? ''));
            $desc = $customDesc !== '' ? $customDesc : ('Compra de itens (lote #' . $batch->id . ')');

            $finance = FinancialRecord::create([
                'type'           => 'expense',
                'category'       => 'Estoque',
                'description'    => $desc,
                'amount'         => $totalReal,
                'amount_estimated' => $totalEstimated ?: null,
                'date'           => $batch->date,
                'payment_status' => $batch->payment_status === 'paid' ? 'paid' : 'pending',
                'user_id'        => $userId,
                'purchase_id'    => $batch->id,
                'meta'           => ['items_count' => $batch->items()->count()],
            ]);

            $batch->load('items');
            return response()->json(array_merge($batch->toArray(), [
                'finance_record_id' => $finance->id,
            ]));
        });
    }
}
