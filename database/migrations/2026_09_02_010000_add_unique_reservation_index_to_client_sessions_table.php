<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un cliente solo puede tener una reserva por sesión: cancelar cambia el
     * estado de la fila, no crea otra. El índice evita duplicados por dobles
     * clics o seeders re-ejecutados, que descuadran los contadores.
     */
    public function up()
    {
        Schema::table('client_sessions', function (Blueprint $table) {
            $table->unique(['client_id', 'session_id'], 'client_sessions_client_session_unique');
        });
    }

    public function down()
    {
        Schema::table('client_sessions', function (Blueprint $table) {
            $table->dropUnique('client_sessions_client_session_unique');
        });
    }
};
