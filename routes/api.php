<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('oauth/token', '\Laravel\Passport\Http\Controllers\AccessTokenController@issueToken');
Route::post('login', 'ApiLoginController@login');
Route::post('logout', 'AuthController@logout');
Route::post('user/password', 'AuthController@changePw');
Route::get('user', 'AuthController@user');
