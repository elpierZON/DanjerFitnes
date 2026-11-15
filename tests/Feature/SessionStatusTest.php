<?php

namespace Tests\Feature;

use App\Exceptions\ReservationException;
use App\Http\Livewire\Dashboard\ClassesList;
use App\Models\ClassType;
use App\Models\Client;
use App\Models\Room;
use App\Models\Session;
use App\Models\Trainer;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * El estado de una clase depende de su franja horaria, no del día.
 *
 * Regresión: statusForDate() marcaba como "en progreso" cualquier clase de hoy,
 * así que una clase creada para hoy a las 21:30 desaparecía del panel del
 * cliente desde la medianoche, cuando en realidad aún no había empezado.
 */
class SessionStatusTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp() : void
    {
        parent::setUp();

        if (!ClassType::exists() || !Room::exists() || !Trainer::exists()) {
            $this->markTestSkipped('Ejecuta "php artisan db:seed" antes de correr estas pruebas.');
        }
    }

    private function makeSession(string $startsAt, int $duration = 60, array $overrides = []) : Session
    {
        $start = \Carbon\Carbon::parse($startsAt);

        return Session::create(array_merge([
            'trainer_id' => Trainer::value('id'),
            'room_id' => Room::value('id'),
            'class_type_id' => ClassType::value('id'),
            'name' => 'Clase de prueba de estado',
            'description' => 'Prueba.',
            'capacity' => 20,
            'date' => $start->format('Y-m-d'),
            'start_datetime' => $start->format('H:i'),
            'duration_minutes' => $duration,
            'status' => Session::STATUS_SCHEDULED,
        ], $overrides));
    }

    public function test_una_clase_de_hoy_que_aun_no_empieza_sigue_programada()
    {
        $session = $this->makeSession(now()->addHours(2)->format('Y-m-d H:i'));

        $this->assertSame(Session::STATUS_SCHEDULED, $session->resolveStatus());
        $this->assertTrue(Session::query()->bookable()->whereKey($session->id)->exists());
    }

    public function test_una_clase_en_curso_esta_en_progreso_y_no_es_reservable()
    {
        $session = $this->makeSession(now()->subMinutes(10)->format('Y-m-d H:i'), 60);

        $this->assertSame(Session::STATUS_IN_PROGRESS, $session->resolveStatus());
        $this->assertFalse(Session::query()->bookable()->whereKey($session->id)->exists());
    }

    public function test_una_clase_terminada_esta_completada()
    {
        $session = $this->makeSession(now()->subHours(3)->format('Y-m-d H:i'), 60);

        $this->assertSame(Session::STATUS_COMPLETED, $session->resolveStatus());
    }

    public function test_refresh_statuses_corrige_los_estados_desactualizados()
    {
        $future = $this->makeSession(now()->addDay()->format('Y-m-d H:i'), 60, ['status' => Session::STATUS_COMPLETED]);
        $past = $this->makeSession(now()->subDay()->format('Y-m-d H:i'), 60, ['status' => Session::STATUS_SCHEDULED]);
        $cancelled = $this->makeSession(now()->addDay()->format('Y-m-d H:i'), 60, ['status' => Session::STATUS_CANCELLED]);

        Session::refreshStatuses();

        $this->assertSame(Session::STATUS_SCHEDULED, $future->fresh()->status);
        $this->assertSame(Session::STATUS_COMPLETED, $past->fresh()->status);
        // Una cancelación es una decisión del admin: no se recalcula
        $this->assertSame(Session::STATUS_CANCELLED, $cancelled->fresh()->status);
    }

    public function test_la_clase_de_hoy_aparece_en_el_panel_del_cliente()
    {
        $type = ClassType::firstOrFail();
        $session = $this->makeSession(
            now()->addHours(2)->format('Y-m-d H:i'),
            60,
            ['class_type_id' => $type->id, 'name' => 'Clase de hoy mas tarde']
        );

        $client = Client::with('user')->firstOrFail();
        $this->actingAs($client->user);

        $sessions = Livewire::test(ClassesList::class)
            ->call('selectClassType', $type->id)
            ->get('sessions');

        $this->assertTrue(
            $sessions->contains('id', $session->id),
            'Una clase de hoy que todavia no empieza debe verse en el panel del cliente.'
        );
    }

    public function test_no_se_puede_reservar_una_clase_que_ya_empezo()
    {
        $session = $this->makeSession(now()->subMinutes(10)->format('Y-m-d H:i'), 60);
        $client = Client::firstOrFail();

        $this->expectException(ReservationException::class);

        app(ReservationService::class)->reserve($client, $session);
    }

    public function test_no_se_puede_reservar_una_clase_cancelada()
    {
        $session = $this->makeSession(
            now()->addDay()->format('Y-m-d H:i'),
            60,
            ['status' => Session::STATUS_CANCELLED]
        );

        $this->expectException(ReservationException::class);

        app(ReservationService::class)->reserve(Client::firstOrFail(), $session);
    }
}
