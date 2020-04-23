<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->timestamp('email_verified_at')->nullable();
            $table->string('username', 50);
            $table->string('password');
            $table->bigInteger("person_id")->unsigned()->nullable();
            $table->integer('status')->default(1);
            $table->rememberToken();
            $table->timestamps();
            $table->bigInteger('zone_id')->unsigned()->nullable();

            $table->foreign('zone_id')->references('id')->on('zones');
            $table->foreign('person_id')->references("id")->on("persons")->onDelete("cascade");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
