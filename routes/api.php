<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\InventoryItemController;
use App\Http\Controllers\Api\FinancialRecordController;
use App\Http\Controllers\Auth\AuthController;

Route::options('{any}', fn () => response()->noContent())->where('any','.*');


Route::post('/login',    [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::get('/ping', fn() => 'pong');


Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', fn (Request $r) => $r->user());


    Route::get   ('/inventory-items',                 [InventoryItemController::class, 'index']);
    Route::post  ('/inventory-items',                 [InventoryItemController::class, 'store']);
    Route::put   ('/inventory-items/{item}',          [InventoryItemController::class, 'update']);
    Route::delete('/inventory-items/{item}',          [InventoryItemController::class, 'destroy']);
    Route::patch ('/inventory-items/{item}/quantity', [InventoryItemController::class, 'changeQuantity']);


    Route::get   ('/finance-records',           [FinancialRecordController::class, 'index']);
    Route::post  ('/finance-records',           [FinancialRecordController::class, 'store']);
    Route::put   ('/finance-records/{finance}', [FinancialRecordController::class, 'update']);
    Route::delete('/finance-records/{finance}', [FinancialRecordController::class, 'destroy']);

    Route::post('/logout', [AuthController::class, 'logout']);
});
