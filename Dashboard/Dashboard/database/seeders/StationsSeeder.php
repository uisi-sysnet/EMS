<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StationsSeeder extends Seeder
{
    public function run(): void
    {
        $connection = DB::connection('aq');

        $connection->table('stations')->truncate();

        $stations = [
            ['station_mn'=>'MN001','station_name'=>'Manila Station','enabled'=>true,'latitude'=>14.5995,'longitude'=>120.9842,'lead_ip'=>'192.168.1.10','lead_port'=>502,'lead_slave'=>1],
            ['station_mn'=>'MN002','station_name'=>'Cebu Station','enabled'=>true,'latitude'=>10.3157,'longitude'=>123.8854,'lead_ip'=>'192.168.1.11','lead_port'=>502,'lead_slave'=>1],
            ['station_mn'=>'MN003','station_name'=>'Davao Station','enabled'=>true,'latitude'=>7.1907,'longitude'=>125.4553,'lead_ip'=>'192.168.2.20','lead_port'=>502,'lead_slave'=>1],
            ['station_mn'=>'MN004','station_name'=>'Baguio Station','enabled'=>true,'latitude'=>16.4023,'longitude'=>120.5960,'lead_ip'=>'192.168.2.21','lead_port'=>502,'lead_slave'=>1],
            ['station_mn'=>'MN005','station_name'=>'Iloilo Station','enabled'=>true,'latitude'=>10.7202,'longitude'=>122.5621,'lead_ip'=>'192.168.3.30','lead_port'=>502,'lead_slave'=>1],
            ['station_mn'=>'MN006','station_name'=>'Zamboanga Station','enabled'=>true,'latitude'=>6.9214,'longitude'=>122.0790,'lead_ip'=>'192.168.3.31','lead_port'=>502,'lead_slave'=>1],
            ['station_mn'=>'MN007','station_name'=>'Tacloban Station','enabled'=>true,'latitude'=>11.2440,'longitude'=>125.0000,'lead_ip'=>'192.168.4.40','lead_port'=>502,'lead_slave'=>1],
            ['station_mn'=>'MN008','station_name'=>'Legazpi Station','enabled'=>true,'latitude'=>13.1391,'longitude'=>123.7438,'lead_ip'=>'192.168.4.41','lead_port'=>502,'lead_slave'=>1],
            ['station_mn'=>'MN009','station_name'=>'Butuan Station','enabled'=>true,'latitude'=>8.9475,'longitude'=>125.5406,'lead_ip'=>'192.168.5.50','lead_port'=>502,'lead_slave'=>1],
            ['station_mn'=>'MN010','station_name'=>'Cagayan de Oro','enabled'=>true,'latitude'=>8.4542,'longitude'=>124.6319,'lead_ip'=>'192.168.5.51','lead_port'=>502,'lead_slave'=>1],
            ['station_mn'=>'MN011','station_name'=>'Bacolod Station','enabled'=>true,'latitude'=>10.6765,'longitude'=>122.9509,'lead_ip'=>'192.168.6.60','lead_port'=>502,'lead_slave'=>1],
            ['station_mn'=>'MN012','station_name'=>'Puerto Princesa','enabled'=>true,'latitude'=>9.7392,'longitude'=>118.7353,'lead_ip'=>'192.168.6.61','lead_port'=>502,'lead_slave'=>1],
            ['station_mn'=>'MN013','station_name'=>'General Santos','enabled'=>true,'latitude'=>6.1164,'longitude'=>125.1716,'lead_ip'=>'192.168.7.70','lead_port'=>502,'lead_slave'=>1],
            ['station_mn'=>'MN014','station_name'=>'Naga Station','enabled'=>true,'latitude'=>13.6218,'longitude'=>123.1948,'lead_ip'=>'192.168.7.71','lead_port'=>502,'lead_slave'=>1],
            ['station_mn'=>'MN015','station_name'=>'Vigan Station','enabled'=>true,'latitude'=>17.5747,'longitude'=>120.3869,'lead_ip'=>'192.168.8.80','lead_port'=>502,'lead_slave'=>1],
        ];

        foreach ($stations as &$station) {
            $station['updated_at'] = now();
        }

        $connection->table('stations')->insert($stations);
    }
}