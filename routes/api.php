<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


/*
|--------------------------------------------------------------------------
| TG WeHook
|--------------------------------------------------------------------------
|
| all the incoming message will come through this route
| 
*/

Route::prefix('telegram/webhooks')->group(function () {
    Route::post('inbound', [App\Http\Controllers\TgBotController::class, 'inbound'])->name('telegram.inbound');
});


Route::post('/wb-inbound', [App\Http\Controllers\WhatsappBotController::class, 'inbound']);
Route::get('/testing', [App\Http\Controllers\TgBotController::class, 'validate_email'])->name('validate_email');
