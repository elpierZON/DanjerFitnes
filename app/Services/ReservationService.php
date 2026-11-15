<?php

namespace App\Services;

use App\Exceptions\ReservationException;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\Session;
use Illuminate\Support\Facades\DB;

/**
 * Punto único donde se resuelven las transiciones de estado de una reserva.
 * Todo cambio de estado queda persistido en la tabla client_sessions.
 */
class ReservationService
{
    /**
     * Reserva de un cliente en una sesión, exista o no.
     */
    public function reservationFor(Client $client, Session $session) : ?ClientSession
    {
        return ClientSession::where('client_id', $client->id)
            ->where('session_id', $session->id)
            ->first();
    }

    /**
     * Cupos ocupados (reservas activas) de una sesión.
     */
    public function occupiedSpots(Session $session) : int
    {
        return ClientSession::forSession($session->id)->active()->count();
    }

    public function availableSpots(Session $session) : int
    {
        return max(0, $session->capacity - $this->occupiedSpots($session));
    }

    /**
     * Crea la reserva o reactiva una previamente cancelada.
     *
     * @throws ReservationException
     */
    public function reserve(Client $client, Session $session) : ClientSession
    {
        return DB::transaction(function () use ($client, $session) {
            // Bloqueo de fila para que dos reservas simultáneas no superen el aforo.
            $session = Session::lockForUpdate()->findOrFail($session->id);

            if ($session->status === Session::STATUS_CANCELLED) {
                throw new ReservationException('Esta clase fue cancelada.');
            }

            // Se compara contra la franja real: la columna `status` puede estar
            // desactualizada si nadie abrió el panel de administración.
            if ($session->resolveStatus() !== Session::STATUS_SCHEDULED) {
                throw new ReservationException('Esta clase ya empezó y no admite reservas.');
            }

            $reservation = $this->reservationFor($client, $session);

            if ($reservation && $reservation->isActive()) {
                throw new ReservationException('Ya tienes una reserva para esta clase.');
            }

            if ($reservation && $reservation->status === ClientSession::STATUS_COMPLETED) {
                throw new ReservationException('Esta clase ya fue completada.');
            }

            if ($this->availableSpots($session) < 1) {
                throw new ReservationException('Esta clase está llena. No hay cupos disponibles.');
            }

            $attributes = [
                'status'       => ClientSession::STATUS_RESERVED,
                'attendance'   => false,
                'reserved_at'  => now(),
                'cancelled_at' => null,
            ];

            if ($reservation) {
                $client->sessions()->updateExistingPivot($session->id, $attributes);
            } else {
                $client->sessions()->attach($session->id, $attributes);
            }

            return $this->reservationFor($client, $session);
        });
    }

    /**
     * El cliente confirma que asistirá.
     *
     * @throws ReservationException
     */
    public function confirm(Client $client, Session $session) : ClientSession
    {
        $reservation = $this->requireReservation($client, $session);

        if ($reservation->status === ClientSession::STATUS_CONFIRMED) {
            throw new ReservationException('Esta reserva ya está confirmada.');
        }

        if ($reservation->status !== ClientSession::STATUS_RESERVED) {
            throw new ReservationException('Solo puedes confirmar una reserva activa.');
        }

        $client->sessions()->updateExistingPivot($session->id, [
            'status' => ClientSession::STATUS_CONFIRMED,
        ]);

        return $this->reservationFor($client, $session);
    }

    /**
     * Cancela la reserva y deja constancia de cuándo se canceló.
     *
     * @throws ReservationException
     */
    public function cancel(Client $client, Session $session) : ClientSession
    {
        $reservation = $this->requireReservation($client, $session);

        if ($reservation->status === ClientSession::STATUS_COMPLETED) {
            throw new ReservationException('No puedes cancelar una clase ya completada.');
        }

        if ($reservation->status === ClientSession::STATUS_CANCELLED) {
            throw new ReservationException('Esta reserva ya estaba cancelada.');
        }

        $client->sessions()->updateExistingPivot($session->id, [
            'status'       => ClientSession::STATUS_CANCELLED,
            'attendance'   => false,
            'cancelled_at' => now(),
        ]);

        return $this->reservationFor($client, $session);
    }

    /**
     * Cierra la reserva tras dictarse la clase, registrando la asistencia.
     *
     * @throws ReservationException
     */
    public function complete(Client $client, Session $session, bool $attended = true) : ClientSession
    {
        $reservation = $this->requireReservation($client, $session);

        if ($reservation->status === ClientSession::STATUS_CANCELLED) {
            throw new ReservationException('No puedes completar una reserva cancelada.');
        }

        $client->sessions()->updateExistingPivot($session->id, [
            'status'     => ClientSession::STATUS_COMPLETED,
            'attendance' => $attended,
        ]);

        return $this->reservationFor($client, $session);
    }

    /**
     * El entrenador pasa lista: marcar asistencia cierra la reserva como completada,
     * desmarcarla la devuelve a confirmada.
     *
     * @throws ReservationException
     */
    public function markAttendance(Session $session, Client $client, bool $attended) : ClientSession
    {
        $reservation = $this->requireReservation($client, $session);

        if ($reservation->status === ClientSession::STATUS_CANCELLED) {
            throw new ReservationException('Esta reserva está cancelada.');
        }

        $client->sessions()->updateExistingPivot($session->id, [
            'attendance' => $attended,
            'status'     => $attended
                ? ClientSession::STATUS_COMPLETED
                : ClientSession::STATUS_CONFIRMED,
        ]);

        return $this->reservationFor($client, $session);
    }

    /**
     * El administrador cancela la clase entera: la sesión queda cancelada y con
     * ella todas sus reservas vigentes, conservando el historial.
     *
     * @return int Cuántas reservas se cancelaron.
     */
    public function cancelSession(Session $session) : int
    {
        return DB::transaction(function () use ($session) {
            $cancelled = ClientSession::forSession($session->id)
                ->active()
                ->update([
                    'status'       => ClientSession::STATUS_CANCELLED,
                    'attendance'   => false,
                    'cancelled_at' => now(),
                    'updated_at'   => now(),
                ]);

            $session->update(['status' => Session::STATUS_CANCELLED]);

            return $cancelled;
        });
    }

    /**
     * Deshace la cancelación de la clase. Las reservas ya canceladas no se
     * resucitan: cada cliente vuelve a reservar si quiere.
     */
    public function reactivateSession(Session $session) : Session
    {
        $session->update([
            'status' => Session::statusFor($session->starts_at, (int) $session->duration_minutes),
        ]);

        return $session;
    }

    /**
     * @throws ReservationException
     */
    private function requireReservation(Client $client, Session $session) : ClientSession
    {
        $reservation = $this->reservationFor($client, $session);

        if (!$reservation) {
            throw new ReservationException('No tienes una reserva para esta clase.');
        }

        return $reservation;
    }
}
