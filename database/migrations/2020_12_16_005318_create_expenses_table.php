<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExpensesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->date('date')->nullable();
            $table->string('category', 50);
            $table->float('monto', 12, 2);
            $table->string('description', 100)->nullable();
            $table->string('image', 75)->nullable();
            $table->bigInteger('user_id')->unsigned();
            $table->integer('status')->default(1);

            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('expenses');
    }
}
