<?php

use Illuminate\Support\Facades\Route;

Route::post('oauth/token', '\Laravel\Passport\Http\Controllers\AccessTokenController@issueToken');
Route::post('login', 'ApiLoginController@login');
Route::post('logout', 'AuthController@logout');
Route::post('user/password', 'AuthController@changePw');
Route::get('user', 'AuthController@user');

Route::namespace('Api')->group(function () {
    Route::get('client/list', 'ClientController@list');
    Route::get('client/search', 'ClientController@search');
    Route::get('client/history', 'ClientController@history');
    Route::post('client/{id}', 'ClientController@update');
    Route::apiResource('client', 'ClientController')->except(['update']);

    // credits
    Route::resource('credit', 'CreditController');

    // payments
    Route::get('payment/only/{id}', 'PaymentController@showByCredit');
    Route::apiResource('payment', 'PaymentController')->only(['index', 'show', 'update', 'destroy']);
});
Route::get('image/{path}/{filename}', 'AdminController@viewImg')->name('show-image');
