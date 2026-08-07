<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadEmsDatabaseConnections();
    }

    private function loadEmsDatabaseConnections(): void
    {
        $path = '/home/system/EMS/scripts/.env';

        if (!File::exists($path)) {
            return; // fail quietly, don't break the dashboard if the file moves
        }

        $vars = [];
        foreach (explode("\n", File::get($path)) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $vars[trim($key)] = trim($value);
        }

        if (empty($vars)) {
            return;
        }

        $host     = $vars['SYSTEM_DB_HOST'] ?? '127.0.0.1';
        $port     = $vars['SYSTEM_DB_PORT'] ?? '5432';
        $user     = $vars['SYSTEM_DB_USER'] ?? null;
        $password = $vars['SYSTEM_DB_PASSWORD'] ?? null;

        // logical name => .env key holding the database name
        $databases = [
            'aq'       => 'AQ_DB_NAME',
            'seismic'  => 'SEISMIC_DB_NAME',
            'sms'      => 'SMS_DB_NAME',
            'api'      => 'API_DB_NAME',
            'logs'     => 'LOG_DB_NAME',
        ];

        foreach ($databases as $connectionName => $envKey) {
            if (empty($vars[$envKey])) {
                continue;
            }

            Config::set("database.connections.$connectionName", [
                'driver'   => 'pgsql',
                'host'     => $host,
                'port'     => $port,
                'database' => $vars[$envKey],
                'username' => $user,
                'password' => $password,
                'charset'  => 'utf8',
                'prefix'   => '',
                'schema'   => 'public',
                'sslmode'  => 'prefer',
            ]);
        }
    }
}