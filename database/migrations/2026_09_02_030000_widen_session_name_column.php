<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `sessions.name` era varchar(25): con STRICT_TRANS_TABLES, cualquier nombre
     * más largo aborta la creación de la clase con SQLSTATE[22001]. 25 caracteres
     * no alcanzan ni para "Entrenamiento Funcional Avanzado".
     *
     * Se usa SQL directo porque ->change() exige doctrine/dbal, que no está
     * instalado en este proyecto.
     */
    public function up()
    {
        DB::statement('ALTER TABLE `sessions` MODIFY `name` VARCHAR(100) NOT NULL');
    }

    public function down()
    {
        DB::statement('ALTER TABLE `sessions` MODIFY `name` VARCHAR(25) NOT NULL');
    }
};
