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

Route::get('/', function () {
    return view('welcome');
})//->middleware("auth")
->name('welcome');

Route::get('/dashboard', function () {
    return view('home');
})
->name('dashboard');

Route::get('dashboard2', function () {
    return view('layouts.dashboardC');
})
->name('dashboard2');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
