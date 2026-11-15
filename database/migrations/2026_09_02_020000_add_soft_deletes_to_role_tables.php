<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las tablas de rol usan borrado lógico: quitarle un rol a un usuario ya no
     * destruye la fila. Antes, un cambio de rol borraba el cliente (y en cascada
     * sus reservas) o el entrenador (y en cascada sus sesiones).
     */
    private array $tables = ['clients', 'trainers', 'administrators'];

    public function up()
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->softDeletes();
            });
        }
    }

    public function down()
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
