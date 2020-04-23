<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePeopleTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->id();
            $table->string("name", 150);
            $table->string("address", 100)->nullable();
            $table->string("lat", 30)->nullable();
            $table->string("lng", 30)->nullable();

            $table->string("address_b", 100)->nullable();
            $table->string("lat_b")->nullable();
            $table->string("lng_b")->nullable();

            $table->string("phone", 13)->nullable();
            $table->string("phone_b", 13)->nullable();
            $table->string("fb", 100)->nullable();

            $table->integer("status")->default(1);
            $table->string("type", 25)->default(\App\Person::TYPE_CLIENT);


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
        Schema::dropIfExists('people');
    }
}
