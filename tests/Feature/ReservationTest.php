<?php

namespace Tests\Feature;

use App\Http\Livewire\Dashboard\ClassesList;
use App\Http\Livewire\Dashboard\MyReservations;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\Session;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Verifica que los cambios de estado de una reserva se persistan en client_sessions
 * y no se queden solo en la interfaz.
 */
class ReservationTest extends TestCase
{
    use DatabaseTransactions;

    private Client $client;

    protected function setUp() : void
    {
        parent::setUp();

        $client = Client::whereHas('user', fn($q) => $q->where('email', 'cliente@gym.com'))->first();

        if (!$client) {
            $this->markTestSkipped('Ejecuta "php artisan db:seed" antes de correr estas pruebas.');
        }

        $this->client = $client;
        $this->actingAs($client->user);
    }

    /** Una sesión futura donde el cliente todavía no tiene reserva. */
    private function freeSession() : Session
    {
        return Session::where('status', 'scheduled')
            ->whereDate('date', '>', now())
            ->whereDoesntHave('reservations', fn($q) => $q->where('client_id', $this->client->id))
            ->firstOrFail();
    }

    private function assertReservationStatus(Session $session, string $status) : void
    {
        $this->assertDatabaseHas('client_sessions', [
            'client_id'  => $this->client->id,
            'session_id' => $session->id,
            'status'     => $status,
        ]);
    }

    public function test_el_dashboard_muestra_la_seccion_de_reservas()
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Mis Reservas');
    }

    public function test_reservar_una_clase_crea_la_fila_en_la_base_de_datos()
    {
        $session = $this->freeSession();

        Livewire::test(ClassesList::class)
            ->call('reserveClass', $session->id);

        $this->assertReservationStatus($session, ClientSession::STATUS_RESERVED);

        $reservation = ClientSession::where('client_id', $this->client->id)
            ->where('session_id', $session->id)
            ->first();

        $this->assertNotNull($reservation->reserved_at);
        $this->assertNull($reservation->cancelled_at);
        $this->assertFalse($reservation->attendance);
    }

    public function test_confirmar_y_cancelar_actualizan_el_estado_en_la_base_de_datos()
    {
        $session = $this->freeSession();

        Livewire::test(MyReservations::class)
            ->call('reactivateReservation', $session->id)
            ->call('confirmReservation', $session->id);

        $this->assertReservationStatus($session, ClientSession::STATUS_CONFIRMED);

        Livewire::test(MyReservations::class)
            ->call('cancelReservation', $session->id);

        $this->assertReservationStatus($session, ClientSession::STATUS_CANCELLED);

        $this->assertNotNull(
            ClientSession::where('client_id', $this->client->id)
                ->where('session_id', $session->id)
                ->first()
                ->cancelled_at
        );
    }

    public function test_no_se_puede_reservar_dos_veces_la_misma_clase()
    {
        $session = $this->freeSession();

        Livewire::test(ClassesList::class)
            ->call('reserveClass', $session->id)
            ->call('reserveClass', $session->id);

        $this->assertSame(
            1,
            ClientSession::where('client_id', $this->client->id)
                ->where('session_id', $session->id)
                ->count()
        );
    }

    public function test_no_se_puede_reservar_una_clase_llena()
    {
        $otherClients = Client::where('id', '!=', $this->client->id)->pluck('id');

        $session = Session::where('status', 'scheduled')
            ->whereDate('date', '>', now())
            ->where('capacity', '<=', $otherClients->count())
            ->firstOrFail();

        // Llenar el aforo con los demás clientes
        ClientSession::where('session_id', $session->id)->delete();

        foreach ($otherClients->take($session->capacity) as $clientId) {
            ClientSession::create([
                'client_id'   => $clientId,
                'session_id'  => $session->id,
                'status'      => ClientSession::STATUS_RESERVED,
                'attendance'  => false,
                'reserved_at' => now(),
            ]);
        }

        Livewire::test(ClassesList::class)
            ->call('reserveClass', $session->id);

        $this->assertDatabaseMissing('client_sessions', [
            'client_id'  => $this->client->id,
            'session_id' => $session->id,
        ]);
    }

    public function test_el_entrenador_pasa_lista_y_completa_la_reserva()
    {
        $session = $this->freeSession();

        Livewire::test(ClassesList::class)->call('reserveClass', $session->id);

        $this->actingAs(User::where('email', 'entrenador@gym.com')->firstOrFail());

        // El entrenador solo ve sus propias clases
        $session->update(['trainer_id' => auth()->user()->trainer->id]);

        Livewire::test(\App\Http\Livewire\Trainer\MyClasses::class)
            ->call('toggleAttendance', $session->id, $this->client->id);

        $this->assertDatabaseHas('client_sessions', [
            'client_id'  => $this->client->id,
            'session_id' => $session->id,
            'status'     => ClientSession::STATUS_COMPLETED,
            'attendance' => true,
        ]);
    }
}
