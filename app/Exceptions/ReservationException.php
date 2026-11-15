<?php

namespace App\Exceptions;

use Exception;

/**
 * Se lanza cuando una transición de estado de reserva no es válida.
 * El mensaje es apto para mostrarse directamente al usuario.
 */
class ReservationException extends Exception
{
}
