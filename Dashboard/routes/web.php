<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StationController;
use App\Http\Controllers\AboutController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ApiFileController;
use App\Http\Controllers\EnvEditorController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\ApiLogController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\RecentLogsController;
use App\Http\Controllers\NetworkController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\SeismicStationController;
use App\Http\Controllers\ServicesController;
use App\Http\Controllers\TerminalAuthController;
use App\Http\Controllers\TelegramSettingsController;

Route::get('/register', [LoginController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [LoginController::class, 'register']);
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware(['role:superAdmin,admin,user'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard/report', [DashboardController::class, 'generateReport'])->name('dashboard.report');
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
});

// Add this to your routes file (web.php) inside the superAdmin middleware group
Route::middleware(['role:superAdmin'])->group(function () {
    Route::post('/change-password', [UserController::class, 'changePassword'])->name('password.change');
});

Route::middleware(['role:superAdmin,admin'])->group(function () {

    Route::get('/about', [AboutController::class, 'about'])->name('about');
    
    // User Management Routes
    Route::get('/user', [UserController::class, 'index'])->name('user.index');
    Route::post('/user', [UserController::class, 'store'])->name('user.store');
    Route::get('/user/{id}/edit', [UserController::class, 'edit'])->name('user.edit');
    Route::put('/user/{id}', [UserController::class, 'update'])->name('user.update');
    Route::delete('/user/{id}', [UserController::class, 'destroy'])->name('user.destroy');

    Route::get('/sms', [SmsController::class, 'index'])->name('sms.index');

    Route::get('/stations', [StationController::class, 'index'])->name('stations.index');
    Route::post('/stations', [StationController::class, 'store'])->name('stations.store');
    Route::delete('/allowed-networks', [ApiKeyController::class, 'destroyIp'])->name('allowed-networks.destroy');
    Route::put('/stations/{station}', [StationController::class, 'update'])->name('stations.update');
    Route::get('/stations/{station}/edit', [StationController::class, 'edit'])->name('stations.edit');
    Route::delete('/stations/{station}', [StationController::class, 'destroy'])->name('stations.destroy');
    Route::post('/stations/{station_mn}/restore', [StationController::class, 'restore'])->name('stations.restore');
    Route::get('/stations/{station_mn}/check-data', [StationController::class, 'checkData'])->name('stations.check-data');

    Route::get('/env-editor', [EnvEditorController::class, 'index'])->name('env.editor');
    Route::get('/load-env', [EnvEditorController::class, 'load'])->name('env.load');
    Route::post('/save-env', [EnvEditorController::class, 'save'])->name('env.save');

    Route::get('/env/mqtt-editor', [EnvEditorController::class, 'mqttIndex'])->name('mqtt.editor');
    Route::get('/env/mqtt/load', [EnvEditorController::class, 'loadMqtt'])->name('env.mqtt.load');
    Route::post('/env/mqtt/save', [EnvEditorController::class, 'saveMqtt'])->name('env.mqtt.save');

    Route::get('/api-editor', [ApiKeyController::class, 'index'])->name('api.editor');
    Route::post('/api-keys/save', [ApiKeyController::class, 'save'])->name('api.keys.save');
    Route::get('/api-keys/generate', [ApiKeyController::class, 'generate'])->name('api.keys.generate');
    Route::delete('/api-keys/{token}', [ApiKeyController::class, 'destroy'])->name('api.keys.destroy');

    Route::post('/allowed-networks', [ApiKeyController::class, 'store'])->name('allowed-networks.store');
    Route::delete('/allowed-networks/{cidr}', [ApiKeyController::class, 'destroyIp'])->name('allowed-networks.destroy');

    Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
    Route::get('/logs/export', [LogController::class, 'exportCsv'])->name('logs.export');
    Route::post('/logs/mark-as-seen', [LogController::class, 'markAsSeen'])->name('logs.mark-as-seen');

    Route::get('/api-logs', [ApiLogController::class, 'index'])->name('api-logs.index');
    Route::get('/api-logs/export', [ApiLogController::class, 'exportCsv'])->name('api-logs.export');
    Route::post('/api-logs/mark-as-seen', [ApiLogController::class, 'markAsSeen'])->name('api-logs.mark-as-seen');

    Route::get('/recent-logs', [RecentLogsController::class, 'index'])->name('recent-logs');
    Route::get('/recent-logs/count', [RecentLogsController::class, 'count'])->name('recent-logs.count');
    Route::post('/mark-log-seen', [RecentLogsController::class, 'markLogAsSeen'])->name('recent-logs.mark-seen');
    Route::post('/mark-all-logs-seen', [RecentLogsController::class, 'markAllAsSeen'])->name('logs.mark-all-seen');

    Route::get('/network', [NetworkController::class, 'index'])->name('network.index');
    Route::get('/network/load', [NetworkController::class, 'load'])->name('network.load');
    Route::post('/network/save', [NetworkController::class, 'save'])->name('network.save');
    Route::post('/network/restart-eth', [NetworkController::class, 'restartEth'])->name('network.restart-eth');

    Route::get('/maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index');
    Route::get('/maintenance/interfaces', [MaintenanceController::class, 'interfaces'])->name('maintenance.interfaces');
    Route::post('/maintenance/ping', [MaintenanceController::class, 'ping'])->name('maintenance.ping');
    Route::post('/maintenance/traceroute', [MaintenanceController::class, 'traceroute'])->name('maintenance.traceroute');   

    Route::get('/seismic-stations', [SeismicStationController::class, 'index'])->name('seismic-stations.index');
    Route::post('/seismic-stations', [SeismicStationController::class, 'store'])->name('seismic-stations.store');
    Route::get('/seismic-stations/{station_id}/edit', [SeismicStationController::class, 'edit']);
    Route::put('/seismic-stations/{station_id}', [SeismicStationController::class, 'update'])->name('seismic-stations.update');
    Route::delete('/seismic-stations/{station_id}', [SeismicStationController::class, 'destroy'])->name('seismic-stations.destroy');

    Route::get('/maintenance/services', [ServicesController::class, 'index'])->name('services.index');
    Route::get('/maintenance/services/status', [ServicesController::class, 'status'])->name('services.status');
    Route::post('/maintenance/services/{service}/action', [ServicesController::class, 'action'])->name('services.action');
    Route::post('/maintenance/terminal/token', [TerminalAuthController::class, 'issue'])->name('terminal.token');
    Route::get('/maintenance/terminal', [ServicesController::class, 'terminal'])->name('services.terminal');
    
    Route::get('/dashboard/report/image', [DashboardController::class, 'generateImageReport'])
    ->name('dashboard.report.image');

    Route::get('/settings/telegram', [TelegramSettingsController::class, 'edit'])->name('settings.telegram.edit');
    Route::put('/settings/telegram', [TelegramSettingsController::class, 'update'])->name('settings.telegram.update');
    Route::post('/settings/telegram/test', [TelegramSettingsController::class, 'test'])->name('settings.telegram.test');
    

});