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
            $table->float('a')->nullable();
            $table->float('b')->nullable();
            $table->float('c')->nullable();
            $table->float('irt_score')->nullable();
            $table->float('theta_awal')->nullable();
            $table->float('theta_akhir')->nullable();
            $table->float('P')->nullable();
            $table->float('Q')->nullable();
            $table->float('I')->nullable();
            $table->float('SE')->nullable();
            $table->float('selisih_SE')->nullable();
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
            $table->dropColumn('a');
            $table->dropColumn('b');
            $table->dropColumn('c');
            $table->dropColumn('irt_score');
            $table->dropColumn('theta_awal');
            $table->dropColumn('theta_akhir');
            $table->dropColumn('P');
            $table->dropColumn('Q');
            $table->dropColumn('I');
            $table->dropColumn('SE');
            $table->dropColumn('selisih_SE');
        });
    }
}
