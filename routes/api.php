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
    Route::apiResource('client', 'ClientController');
});
