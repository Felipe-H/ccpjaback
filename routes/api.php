<?php

use App\Http\Controllers\Api\InventoryItemController;
use App\Http\Controllers\Api\FinancialRecordController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['auth:sanctum'])->group(function () {

    // INVENTORY
    Route::get   ('/inventory-items',                 [InventoryItemController::class, 'index']);
    Route::post  ('/inventory-items',                 [InventoryItemController::class, 'store']);
    Route::put   ('/inventory-items/{item}',          [InventoryItemController::class, 'update']);
    Route::delete('/inventory-items/{item}',          [InventoryItemController::class, 'destroy']);
    Route::patch ('/inventory-items/{item}/quantity', [InventoryItemController::class, 'changeQuantity']);

    // FINANCE
    Route::get   ('/finance-records',                 [FinancialRecordController::class, 'index']);
    Route::post  ('/finance-records',                 [FinancialRecordController::class, 'store']);
    Route::put('/finance-records/{finance}', [FinancialRecordController::class, 'update']);
    Route::delete('/finance-records/{finance}', [FinancialRecordController::class, 'destroy']);
});
