<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up()
  {
      Schema::table('stations', function (Blueprint $table) {
          $table->dropUnique('stations_lead_ip_unique');
      });
  }

  public function down()
  {
      Schema::table('stations', function (Blueprint $table) {
          $table->unique('lead_ip', 'stations_lead_ip_unique');
      });
  }
};