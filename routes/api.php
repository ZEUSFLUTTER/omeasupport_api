<?php

use App\Http\Controllers\api\AuthController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TechnicianController;
use App\Http\Controllers\Intervention;
use App\Http\Controllers\RapportController;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InterventionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/edit-profile', [AuthController::class, 'edit']);
    Route::post('/change-password', [AuthController::class, 'updatePassword']);
    Route::delete('/logout', [AuthController::class, 'logout']);
});

// 🎫 Routes liées aux TICKETS (protégées par Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::get('/tickets/{id}', [TicketController::class, 'show']);
    Route::delete('/client/tickets/{id}', [TicketController::class, 'destroy']);
    Route::put('/tickets/{id}/reschedule', [TicketController::class, 'reschedule']);
    Route::get('/tickets/{id}/payment', [TicketController::class, 'paymentLink']);


    // partie technicien

    Route::get('/read', [TechnicianController::class, 'read']);
    Route::post('/tickets/{id}/postuler', [TechnicianController::class, 'postulerTicket']);
    Route::post('/tickets/{id}/plan', [TechnicianController::class, 'planTicket']);
    Route::post('/tickets/{id}/start', [TechnicianController::class, 'startIntervention']);
    Route::post('/tickets/{id}/end', [TechnicianController::class, 'endIntervention']);
    Route::put('/tickets/{ticket}/rapport', [RapportController::class, 'store']);


    
});
