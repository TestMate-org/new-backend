<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
 */

Route::middleware(['basicAuth'])->group(function () {
    Route::get('/system-administration-testmate-cbt', 'System\SystemController@index')->name('system.testmate.index');
    Route::get('/system-administration-testmate-cbt/change-ip', 'System\SystemController@changeIP')->name('system.testmate.change.ip');
    Route::post('/system-administration-testmate-cbt/change-ip', 'System\SystemController@storeChangeIP')->name('system.testmate.change.ip.store');
    Route::get('/system-administration-testmate-cbt/check-update', 'System\SystemController@checkUpdate')->name('system.testmate.check.update');
});

Route::view('/{any}', 'ujian')->where('any', '.*');

// Route::get('/irf-graph', [ItemResponseController::class, 'showIRFGraph']);
