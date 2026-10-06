<?php

use App\Http\Controllers\ChatbotController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/chat', [ChatbotController::class, 'index'])->name('chat');
Route::get('/chat/health', [ChatbotController::class, 'health'])->name('chat.health');
Route::post('/chat/send', [ChatbotController::class, 'send'])->middleware('throttle:chat')->name('chat.send');
Route::get('/widget', [ChatbotController::class, 'widget'])->name('chat.widget');
Route::post('/api/chat/send', [\App\Http\Controllers\ChatbotController::class, 'send'])->middleware(['widget.api', 'throttle:chat'])->name('api.chat.send');
