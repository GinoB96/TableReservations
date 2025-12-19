<?php

use App\Http\Controllers\ReservationRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/reservations-per-day', [ReservationRequestController::class, 'getReservationsPerDay']);
Route::post('/reservation-requests', [ReservationRequestController::class, 'store']);
