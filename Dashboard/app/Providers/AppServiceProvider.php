<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

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
        $path = config('database.ems_env_path');

        if (!$path || !File::exists($path)) {
            // don't break the dashboard if the file moves, but leave a trace
            Log::warning("EMS scripts .env not found at '{$path}'; EMS database connections are not configured.");
            return;
        }

        $vars = [];
        foreach (preg_split('/\r\n|\r|\n/', File::get($path)) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $trimmed, 2);
            $key = trim(preg_replace('/^export\s+/', '', $key));
            $vars[$key] = $this->parseEnvValue($value);
        }

        if (empty($vars)) {
            Log::warning("EMS scripts .env at '{$path}' is empty or unreadable.");
            return;
        }

        $host     = $vars['SYSTEM_DB_HOST'] ?? '127.0.0.1';
        $port     = $vars['SYSTEM_DB_PORT'] ?? '5432';
        $user     = $vars['SYSTEM_DB_USER'] ?? null;
        $password = $vars['SYSTEM_DB_PASSWORD'] ?? null;

        // logical name => .env key holding the database name
        $databases = [
            'aq'          => 'AQ_DB_NAME',
            'seismic'     => 'SEISMIC_DB_NAME',
            'sms'         => 'SMS_DB_NAME',
            'api'         => 'API_DB_NAME',
            'logs'        => 'LOG_DB_NAME',
            'water_level' => 'WATER_LEVEL_DB_NAME', // <-- added
        ];

        foreach ($databases as $connectionName => $envKey) {
            if (empty($vars[$envKey])) {
                Log::warning("EMS scripts .env is missing {$envKey}; '{$connectionName}' connection is not configured.");
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

    /**
     * Strip surrounding quotes, and inline comments from unquoted values,
     * the same way python-dotenv reads the file for the ingestion services.
     */
    private function parseEnvValue(string $value): string
    {
        $value = trim($value);

        if (preg_match('/^([\'"])(.*)\1$/s', $value, $m)) {
            return $m[2];
        }

        return trim(preg_replace('/\s+#.*$/', '', $value));
    }
}
