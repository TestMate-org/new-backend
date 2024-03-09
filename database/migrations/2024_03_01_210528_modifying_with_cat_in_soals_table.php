<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyingWithCatInSoalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('soals', function (Blueprint $table) {
            $table->float('daya_beda')->nullable();
            $table->float('tingkat_kesulitan')->nullable();
            $table->float('tebak_parameter')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('soals', function (Blueprint $table) {
            $table->dropColumn('tebak_parameter');
            $table->dropColumn('tingkat_kesulitan');
            $table->dropColumn('daya_beda');
        });
    }
}
