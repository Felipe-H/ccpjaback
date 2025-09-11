<?php

use App\Http\Controllers\Api\EventInventoryController;
use App\Http\Controllers\Api\EventsController;
use App\Http\Controllers\Api\EventsItemController;
use App\Http\Controllers\Api\EventsPendingController;
use App\Http\Controllers\Api\PurchaseController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TokenAuthController;
use App\Http\Controllers\Api\InventoryItemController;
use App\Http\Controllers\Api\FinancialRecordController;

Route::options('/{any}', fn () => response()->noContent())->where('any', '.*');

Route::post('/login',    [TokenAuthController::class, 'login']);
Route::post('/register', [TokenAuthController::class, 'register']);

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

    Route::get   ('/purchases',  [PurchaseController::class, 'index']);
    Route::post  ('/purchases',  [PurchaseController::class, 'store']);


    Route::get   ('/events',           [EventsController::class, 'index']);
    Route::post  ('/events',           [EventsController::class, 'store']);
    Route::get   ('/events/{event}',   [EventsController::class, 'show']);
    Route::put   ('/events/{event}',   [EventsController::class, 'update']);
    Route::delete('/events/{event}',   [EventsController::class, 'destroy']);
    Route::post  ('/events/{event}/confirm',  [EventsController::class, 'confirm']);
    Route::post  ('/events/{event}/cancel',   [EventsController::class, 'cancel']);
    Route::post  ('/events/{event}/finalize', [EventsController::class, 'finalize']);


    Route::get   ('/events/{event}/items',                    [EventsItemController::class, 'index']);
    Route::post  ('/events/{event}/items',                    [EventsItemController::class, 'store']);
    Route::put   ('/events/{event}/items/{eventItem}',        [EventsItemController::class, 'update']);
    Route::delete('/events/{event}/items/{eventItem}',        [EventsItemController::class, 'destroy']);

    Route::get   ('/events/{event}/pendings',                 [EventsPendingController::class, 'index']);
    Route::post  ('/events/{event}/pendings',                 [EventsPendingController::class, 'store']);
    Route::put   ('/events/{event}/pendings/{pending}',       [EventsPendingController::class, 'update']);
    Route::delete('/events/{event}/pendings/{pending}',       [EventsPendingController::class, 'destroy']);


    Route::get ('/events/{event}/inventory-view', [EventInventoryController::class, 'view']);
    Route::post('/events/{event}/items/sync',      [EventInventoryController::class, 'sync']);
});
