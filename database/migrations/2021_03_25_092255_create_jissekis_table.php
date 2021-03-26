<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJissekisTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('jissekis', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('project');
            $table->string('function');
            $table->string('output');
            $table->date('date');
            $table->float('hour', 5, 2); //99.25Hを想定
            $table->string('user');
            $table->text('comments');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('jissekis');
    }
}
