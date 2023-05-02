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
Route::get('app/routes', function() {
    \Artisan::call('route:list');
    return "<pre>" . \Artisan::output() . "</pre>";
});
Route::get('oauth/redirect', 'App\Http\Controllers\Auth\LoginController@redirectToProvider');
Route::get('oauth/callback', 'App\Http\Controllers\Auth\LoginController@handleProviderCallback');
