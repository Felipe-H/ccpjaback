<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TokenAuthController;
use App\Http\Controllers\Api\InventoryItemController;
use App\Http\Controllers\Api\FinancialRecordController;

Route::options('/{any}', fn () => response()->noContent())->where('any', '.*');

Route::post('/login',    [TokenAuthController::class, 'login']);
Route::post('/register', [TokenAuthController::class, 'register']); // <- aqui

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me',      [TokenAuthController::class, 'me']);
    Route::post('/logout', [TokenAuthController::class, 'logout']);

    Route::get   ('/inventory-items',                 [InventoryItemController::class, 'index']);
    Route::post  ('/inventory-items',                 [InventoryItemController::class, 'store']);
    Route::put   ('/inventory-items/{item}',          [InventoryItemController::class, 'update']);
    Route::delete('/inventory-items/{item}',          [InventoryItemController::class, 'destroy']);
    Route::patch ('/inventory-items/{item}/quantity', [InventoryItemController::class, 'changeQuantity']);

    Route::get   ('/finance-records',           [FinancialRecordController::class, 'index']);
    Route::post  ('/finance-records',           [FinancialRecordController::class, 'store']);
    Route::put   ('/finance-records/{finance}', [FinancialRecordController::class, 'update']);
    Route::delete('/finance-records/{finance}', [FinancialRecordController::class, 'destroy']);
});
