<?php

use App\Events\DriverOnline;
use Illuminate\Support\Facades\Route;

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

Route::get('payment/success', function () {
    return view('welcome');
})->name('payment.success');

Route::get('payment/failure', function () {
    return view('welcome');
})->name('payment.failure');