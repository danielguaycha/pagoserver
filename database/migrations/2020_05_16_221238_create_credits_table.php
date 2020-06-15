<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCreditsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('credits', function (Blueprint $table) {
            $table->id();

            $table->decimal("utilidad", 8, 2);  // porcentaje de ganancia
            $table->string('plazo', 25)->default(\App\Credit::PLAZO_SEMANAL); // plazo para pagar  (Semanal, Quincenal, Mensual)
            $table->string("cobro", 25); // tiempo de cobro (Diario, semanal, quincenal)
            $table->integer('status')->default(\App\Credit::STATUS_ACTIVO);
            $table->string('description', 100)->nullable();

            // money values
            $table->decimal('monto', 13, 2);  // monto a prestar
            $table->double('total_utilidad', 10, 2)->default(0);
            $table->double('total', 10, 2)->default(0);

            $table->double('pagos_de', 10, 2)->default(0);
            $table->double('pagos_de_last', 10, 2)->default(0);
            $table->integer('n_pagos')->default(0);

            $table->bigInteger('zone_id')->unsigned()->nullable();
            $table->bigInteger('person_id')->unsigned();
            $table->bigInteger('user_id')->unsigned();
            $table->bigInteger('guarantor_id')->unsigned()->nullable();

            $table->date('f_inicio')->nullable();
            $table->date('f_fin')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->string('prenda_img', 60)->nullable();
            $table->string('prenda_detail', 150)->nullable();

            $table->foreign('zone_id')->references('id')->on('zones');
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('person_id')->references('id')->on('persons');
            $table->foreign('guarantor_id')->references('id')->on('persons');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('credits');
    }
}
