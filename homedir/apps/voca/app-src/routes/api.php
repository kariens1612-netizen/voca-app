<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\PaymentNotificationController;
use Illuminate\Support\Facades\Route;

Route::post('/bookings', [BookingController::class, 'store'])->middleware('module:bookings');
Route::post('/bookings/{booking}/pay', [BookingController::class, 'pay'])->middleware('module:bookings');
Route::post('/payments/ozow/notify', [PaymentNotificationController::class, 'ozow'])->name('payments.ozow.notify');
Route::post('/payments/payfast/notify', [PaymentNotificationController::class, 'payfast'])->name('payments.payfast.notify');
