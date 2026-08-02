<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SmsMessage;
use Carbon\Carbon;

class SmsMessagesSeeder extends Seeder
{
    public function run(): void
    {
        $senders = [
            'Earthquake Detection Unit',
            'Weather Alert Service',
            'Power Grid Monitor',
            'Security System A',
        ];

        $sampleMessages = [
            'Your OTP code is 524113.',
            'This code expires in 5 minutes.',
            'Please enter it on the website.',
            'Rain expected tomorrow with 80% probability.',
            'Power fluctuation detected at substation 4.',
            'System reboot completed successfully.',
            'Alert: seismic activity detected magnitude 3.2.',
            'Maintenance window scheduled for 02:00 AM.',
            'Battery level low in sensor #12.',
            'Firmware update available for gateway.',
        ];

        $now = Carbon::now();

        foreach ($senders as $sender) {
            // Each sender gets 3-6 messages
            $count = rand(3, 6);
            for ($i = 0; $i < $count; $i++) {
                SmsMessage::create([
                    'received_at' => $now->subMinutes(rand(1, 60)),
                    'sender' => $sender,
                    'modem_timestamp' => now()->subMinutes(rand(1, 60))->format('Y-m-d H:i:s'),
                    'raw_body' => $sampleMessages[array_rand($sampleMessages)],
                    'parsed_ok' => (bool) rand(0, 1),
                    'parse_error' => rand(0, 1) ? 'Invalid format' : null,
                    'station_id' => 'STN-' . rand(100, 999),
                ]);
            }
        }
    }
}