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
            $table->float('a')->nullable()->default(1.0);
            $table->float('b')->nullable()->default(1.0);
            $table->float('c')->nullable()->default(0.25);
            $table->float('a_calibrated')->nullable()->default(1.0);
            $table->float('b_calibrated')->nullable()->default(1.0);
            $table->float('c_calibrated')->nullable()->default(0.25);
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
            $table->dropColumn('c_calibrated');
            $table->dropColumn('b_calibrated');
            $table->dropColumn('a_calibrated');
            $table->dropColumn('c');
            $table->dropColumn('b');
            $table->dropColumn('a');
        });
    }
}
