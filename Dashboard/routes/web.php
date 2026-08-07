<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\ApiFileController;
use App\Http\Controllers\EnvEditorController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\ApiLogController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\RecentLogsController;
use App\Http\Controllers\NetworkController;

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware(['role:administrator,user'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('home');
});

Route::middleware(['role:administrator'])->group(function () {

    Route::get('/about', [AboutController::class, 'about'])->name('about');

    Route::get('/sms', [SmsController::class, 'index'])->name('sms.index');

    Route::get('/env-editor', [EnvEditorController::class, 'index'])->name('env.editor');
    Route::get('/load-env', [EnvEditorController::class, 'load'])->name('env.load');
    Route::post('/save-env', [EnvEditorController::class, 'save'])->name('env.save');

    Route::get('/env/mqtt-editor', [EnvEditorController::class, 'mqttIndex'])->name('mqtt.editor');
    Route::get('/env/mqtt/load', [EnvEditorController::class, 'loadMqtt'])->name('env.mqtt.load');
    Route::post('/env/mqtt/save', [EnvEditorController::class, 'saveMqtt'])->name('env.mqtt.save');

    Route::get('/api-editor', [ApiKeyController::class, 'index'])->name('api.editor');
    Route::post('/api-keys/generate', [ApiKeyController::class, 'generate'])->name('api.keys.generate');
    Route::post('/api-keys/save', [ApiKeyController::class, 'save'])->name('api.keys.save');
    Route::delete('/api-keys/{token}', [ApiKeyController::class, 'destroy'])->name('api.keys.destroy');

    Route::post('/allowed-networks', [ApiKeyController::class, 'store'])->name('allowed-networks.store');
    Route::delete('/allowed-networks/{cidr}', [ApiKeyController::class, 'destroyIp'])->name('allowed-networks.destroy');

    Route::get('/logs', [App\Http\Controllers\LogController::class, 'index'])->name('logs.index');
    Route::get('/api-logs', [ApiLogController::class, 'index'])->name('api-logs.index');

    Route::get('/recent-logs', [RecentLogsController::class, 'index'])->name('recent-logs');
    Route::get('/recent-logs/count', [RecentLogsController::class, 'count'])->name('recent-logs.count');

    Route::get('/network', [NetworkController::class, 'index'])->name('network.index');
    Route::post('/network/load', [NetworkController::class, 'load'])->name('network.load');
    Route::post('/network/save', [NetworkController::class, 'save'])->name('network.save');
    Route::post('/network/restart-eth', [NetworkController::class, 'restartEth'])->name('network.restart-eth');
});