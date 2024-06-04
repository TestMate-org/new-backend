<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyingHasilUjiansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('hasil_ujians', function (Blueprint $table) {
            $table->float('theta_awal')->nullable();
            $table->float('theta_akhir')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hasil_ujians', function (Blueprint $table) {
            $table->dropColumn('theta_akhir');
            $table->dropColumn('theta_awal');
        });
    }
}
