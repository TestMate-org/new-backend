<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyingWithIrtInJawabanPesertasTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('jawaban_pesertas', function (Blueprint $table) {
            $table->integer('urutan')->nullable();
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
        Schema::table('jawaban_pesertas', function (Blueprint $table) {
            $table->dropColumn('theta_akhir');
            $table->dropColumn('theta_awal');
            $table->dropColumn('urutan');
        });
    }
}
