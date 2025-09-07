<?php

// app/Http/Controllers/Api/FinancialRecordController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancialRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinancialRecordController extends Controller
{
    public function index() {
        return FinancialRecord::orderBy('id','desc')->get();
    }

    public function store(Request $req) {
        $data = $req->validate([
            'type' => ['required', Rule::in(['income','expense'])],
            'category' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'nullable|date',
            'payment_status' => ['required', Rule::in(['paid','pending','estimated'])],
        ]);
        $data['user_id'] = $req->user()->id ?? null;
        return FinancialRecord::create($data);
    }

    public function update(Request $req, FinancialRecord $finance): ?FinancialRecord
    {
        $data = $req->validate([
            'type' => [ 'sometimes','required', Rule::in(['income','expense']) ],
            'category' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric|min:0.01',
            'date' => 'nullable|date',
            'payment_status' => ['sometimes','required', Rule::in(['paid','pending','estimated'])],
        ]);
        $finance->update($data);
        return $finance->fresh();
    }

    public function destroy(FinancialRecord $finance) {
        $finance->delete();
        return response()->noContent();
    }
}


