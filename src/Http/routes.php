<?php

use Illuminate\Support\Facades\Route;

Route::post('/close-tab', 'TabGuardController@closeTab')->name('tab-guard.close-tab');
Route::get('/tab-info', 'TabGuardController@getTabInfo')->name('tab-guard.tab-info');
Route::post('/heartbeat', 'TabGuardController@heartbeat')->name('tab-guard.heartbeat');
Route::get('/status', 'TabGuardController@status')->name('tab-guard.status');
