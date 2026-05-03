<?php

use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\ChatController;
use Illuminate\Support\Facades\Route;

Route::post('/chat/send', [ChatController::class, 'store']);
Route::get('/chat/history', [ChatController::class, 'index']);
Route::delete('/chat/history', [ChatController::class, 'destroy']);
Route::post('/auth/register', [RegisterController::class, 'store']);
Route::post('/auth/login', [LoginController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/user', [AuthController::class, 'getUser']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/admin/dashboard', [AdminController::class, 'getDashboardStats']);
    Route::get('/admin/reports', [AdminController::class, 'getAllReports']);
    Route::get('/admin/reports/export', [AdminController::class, 'exportReports']);
    Route::get('/admin/reports/{id}', [AdminController::class, 'getReportDetail']);
    Route::post('/admin/reports/{id}/reply', [AdminController::class, 'replyToUser']);
    Route::post('/admin/reports/{id}/close', [AdminController::class, 'closeCase']);
});
