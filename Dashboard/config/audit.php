<?php

/*
|--------------------------------------------------------------------------
| Audit Log
|--------------------------------------------------------------------------
|
| App\Http\Middleware\AuditTrail records every POST/PUT/PATCH/DELETE made
| in the dashboard. This file gives each action its category and a readable
| description. {placeholders} are filled from the route parameters
| (e.g. {station}) and, when present, the submitted name fields.
|
| Actions not listed here are still recorded, under "other", with the
| method and path as the description — so a new feature is never silently
| missing from the audit trail. List it here to give it a proper name.
|
*/

return [

    // Routes that are never recorded: housekeeping clicks and high-frequency
    // controls that would bury real actions (each PTZ press is two requests).
    'ignore' => [
        'logs.mark-as-seen',
        'api-logs.mark-as-seen',
        'recent-logs.mark-seen',
        'logs.mark-all-seen',
        'cameras.ptz',
    ],

    // Input fields whose values are safe and useful to keep (e.g. which
    // service was restarted). Every other submitted field is recorded by
    // name only, never its value — passwords, keys and secrets included.
    'keep_values' => [
        'action', 'username', 'role', 'name', 'station_name', 'station_mn', 'station_id',
        'location', 'enabled', 'report_interval_minutes', 'sim_number', 'lead_ip',
        'ip_address', 'interface', 'host', 'target', 'device_type',
    ],

    // Route parameters that are secrets; only their last 4 characters are kept.
    'mask_parameters' => ['token'],

    'routes' => [
        // Authentication (login/logout/register have no route names)
        'POST login'                => ['authentication', 'Signed in'],
        'POST logout'               => ['authentication', 'Signed out'],
        'POST register'             => ['authentication', 'Registered an account'],
        'password.change'           => ['security', 'Changed a user password'],

        // Users
        'user.store'                => ['users', 'Created user {name}'],
        'user.update'               => ['users', 'Updated user #{id} {name}'],
        'user.destroy'              => ['users', 'Deleted user #{id}'],

        // Stations
        'inventory.stations.store'    => ['stations', 'Added air quality station {name}'],
        'inventory.stations.update'   => ['stations', 'Updated air quality station {station}'],
        'inventory.stations.destroy'  => ['stations', 'Deleted air quality station {station}'],
        'inventory.stations.restore'  => ['stations', 'Restored air quality station {station_mn}'],
        'inventory.water-level-stations.store'   => ['stations', 'Added water level station {name}'],
        'inventory.water-level-stations.update'  => ['stations', 'Updated water level station {station_mn}'],
        'inventory.water-level-stations.destroy' => ['stations', 'Deleted water level station {station_mn}'],
        'inventory.water-level-stations.restore' => ['stations', 'Restored water level station {station_mn}'],
        'seismic-stations.store'      => ['stations', 'Added seismic station {name}'],
        'seismic-stations.update'     => ['stations', 'Updated seismic station {station_id}'],
        'seismic-stations.destroy'    => ['stations', 'Deleted seismic station {station_id}'],

        // Cameras
        'inventory.cameras.store'   => ['cameras', 'Added camera {name}'],
        'inventory.cameras.update'  => ['cameras', 'Updated camera #{id} {name}'],
        'inventory.cameras.destroy' => ['cameras', 'Deleted camera #{id}'],
        'inventory.cameras.import'  => ['cameras', 'Imported cameras from a file'],
        'cameras.store'             => ['cameras', 'Added camera {name}'],
        'cameras.update'            => ['cameras', 'Updated camera {camera} {name}'],
        'cameras.destroy'           => ['cameras', 'Deleted camera {camera}'],
        'cameras.refresh'           => ['cameras', 'Refreshed camera {camera} from ONVIF'],

        // Settings
        'env.save'                  => ['settings', 'Changed service settings (scripts/.env)'],
        'env.mqtt.save'             => ['settings', 'Changed MQTT settings'],
        'api.keys.save'             => ['security', 'Saved API keys'],
        'api.keys.destroy'          => ['security', 'Deleted API key ending {token}'],
        'allowed-networks.store'    => ['security', 'Allowed API access from a network'],
        'allowed-networks.destroy'  => ['security', 'Removed allowed network {cidr}'],
        'settings.telegram.update'  => ['settings', 'Changed Telegram alert settings'],
        'settings.telegram.test'    => ['settings', 'Sent a Telegram test message'],
        'settings.sentinel.update'  => ['settings', 'Changed Uplink Sentinel link settings'],
        'settings.sentinel.test'    => ['settings', 'Tested the Uplink Sentinel connection'],
        'settings.calibration.store'   => ['settings', 'Added calibration API {name}'],
        'settings.calibration.update'  => ['settings', 'Updated calibration API #{id}'],
        'settings.calibration.destroy' => ['settings', 'Deleted calibration API #{id}'],
        'settings.calibration.test'    => ['settings', 'Tested calibration API'],

        // Network
        'network.save'              => ['network', 'Changed network configuration'],
        'network.restart-eth'       => ['network', 'Restarted the Ethernet interface'],

        // Services and maintenance
        'services.action'           => ['services', 'Ran {action} on service {service}'],
        'terminal.token'            => ['security', 'Opened the web terminal'],
        'maintenance.ping'          => ['maintenance', 'Ran ping to {host}{target}'],
        'maintenance.traceroute'    => ['maintenance', 'Ran traceroute to {host}{target}'],
    ],
];
