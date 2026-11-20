<?php

namespace Tests\Feature;

use App\Http\Livewire\Trainer\MyClasses;
use App\Models\ClientSession;
use App\Models\Trainer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Un entrenador solo puede ver y pasar lista en sus propias clases. Los ids de
 * clase llegan desde el navegador, así que no basta con no mostrarle botones.
 */
class TrainerClassOwnershipTest extends TestCase
{
    use DatabaseTransactions;

    private ClientSession $reservation;
    private Trainer $owner;
    private Trainer $stranger;

    protected function setUp() : void
    {
        parent::setUp();

        $reservation = ClientSession::active()->with('session.trainer.user')->first();
        $owner = $reservation?->session?->trainer;
        $stranger = $owner ? Trainer::with('user')->where('id', '!=', $owner->id)->first() : null;

        if (!$reservation || !$owner || !$stranger) {
            $this->markTestSkipped('Ejecuta "php artisan db:seed" antes de correr estas pruebas.');
        }

        $this->reservation = $reservation;
        $this->owner = $owner;
        $this->stranger = $stranger;
    }

    public function test_un_entrenador_no_ve_los_alumnos_de_una_clase_ajena()
    {
        $this->actingAs($this->stranger->user);

        Livewire::test(MyClasses::class)
            ->call('viewStudents', $this->reservation->session_id)
            ->assertSet('selectedClassId', null)
            ->assertSet('showStudentsModal', false);
    }

    public function test_un_entrenador_no_puede_pasar_lista_en_una_clase_ajena()
    {
        $this->actingAs($this->stranger->user);

        Livewire::test(MyClasses::class)
            ->call('toggleAttendance', $this->reservation->session_id, $this->reservation->client_id);

        $this->assertDatabaseHas('client_sessions', [
            'id'         => $this->reservation->id,
            'status'     => $this->reservation->status,
            'attendance' => $this->reservation->attendance,
        ]);
    }

    public function test_el_entrenador_de_la_clase_si_puede_pasar_lista()
    {
        $this->actingAs($this->owner->user);

        Livewire::test(MyClasses::class)
            ->call('viewStudents', $this->reservation->session_id)
            ->assertSet('showStudentsModal', true)
            ->call('toggleAttendance', $this->reservation->session_id, $this->reservation->client_id);

        $attended = !$this->reservation->attendance;

        $this->assertDatabaseHas('client_sessions', [
            'id'         => $this->reservation->id,
            'status'     => $attended ? ClientSession::STATUS_COMPLETED : ClientSession::STATUS_CONFIRMED,
            'attendance' => $attended,
        ]);
    }
}
