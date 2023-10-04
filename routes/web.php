<?php

use Illuminate\Support\Facades\Route;
use App\Models\TGSessions;
use App\Models\TGSheduleList;
use App\Services\DataScrapingService;
use Carbon\Carbon;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/scrape-data', [App\Http\Controllers\WebScrapingController::class, 'scrapeData']);
Route::get('/testing-query', function (DataScrapingService $service) {
    return $service->scrapeData('Chennai', 'Aruppukkottai', '2023-10-08');
})->name('testing-query');
