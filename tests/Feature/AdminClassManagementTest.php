<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\ClassManagement;
use App\Models\ClassType;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\Room;
use App\Models\Session;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Gestión de clases del panel de administración.
 *
 * El bug principal: resetForm() no limpiaba selectedSessionId, así que tras
 * editar una clase el botón "Crear" ejecutaba updateSession() y sobrescribía
 * la clase editada en lugar de crear una nueva.
 */
class AdminClassManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp() : void
    {
        parent::setUp();

        $admin = User::where('email', 'admin@gym.com')->first();

        if (!$admin || Session::count() === 0) {
            $this->markTestSkipped('Ejecuta "php artisan db:seed" antes de correr estas pruebas.');
        }

        $this->actingAs($admin);
    }

    /** Datos válidos para el formulario de creación. */
    private function formData(array $overrides = []) : array
    {
        return array_merge([
            'trainerId' => Trainer::value('id'),
            'roomId' => Room::value('id'),
            'classTypeId' => ClassType::value('id'),
            'sessionName' => 'Clase de prueba',
            'sessionDescription' => 'Descripción de la clase de prueba.',
            'startDatetime' => now()->addWeek()->format('Y-m-d\TH:i'),
            'durationMinutes' => 60,
        ], $overrides);
    }

    private function fill($component, array $data)
    {
        foreach ($data as $property => $value) {
            $component->set($property, $value);
        }

        return $component;
    }

    public function test_el_panel_de_administracion_carga()
    {
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Gestión de Clases', false);
    }

    public function test_crear_una_clase_despues_de_editar_no_sobrescribe_la_editada()
    {
        $existing = Session::whereDate('date', '>', now())->firstOrFail();
        $originalName = $existing->name;
        $countBefore = Session::count();

        $component = Livewire::test(ClassManagement::class)
            // 1. El admin edita una clase existente
            ->call('editSession', $existing->id)
            // 2. Cierra el modal sin guardar
            ->call('closeCreateModal')
            // 3. Abre "Nueva Clase"
            ->call('openCreateModal');

        // El id anterior no debe seguir colgado: es lo que provocaba el update
        $component->assertSet('selectedSessionId', null);

        $this->fill($component, $this->formData(['sessionName' => 'Clase recien creada']))
            ->call('createSession');

        // Se creó una nueva...
        $this->assertSame($countBefore + 1, Session::count());
        $this->assertDatabaseHas('sessions', ['name' => 'Clase recien creada']);

        // ...y la que se había editado sigue intacta
        $this->assertSame($originalName, $existing->fresh()->name);
    }

    public function test_editar_carga_una_fecha_valida_y_guarda_los_cambios()
    {
        $session = Session::whereDate('date', '>', now())->firstOrFail();

        $component = Livewire::test(ClassManagement::class)
            ->call('editSession', $session->id);

        // Antes se generaba "2026-09-05T2026-09-01 14:30:00" y la validación fallaba
        $component->assertSet(
            'startDatetime',
            $session->date->format('Y-m-d') . 'T' . $session->start_datetime->format('H:i')
        );

        $component->set('sessionName', 'Nombre actualizado')->call('updateSession');

        $component->assertHasNoErrors();
        $this->assertSame('Nombre actualizado', $session->fresh()->name);
    }

    public function test_la_capacidad_se_toma_de_la_sala()
    {
        $room = Room::orderByDesc('capacity')->firstOrFail();

        Livewire::test(ClassManagement::class)
            ->call('openCreateModal')
            ->set('trainerId', Trainer::value('id'))
            ->set('roomId', $room->id)
            ->set('classTypeId', ClassType::value('id'))
            ->set('sessionName', 'Clase con aforo de sala')
            ->set('sessionDescription', 'Descripción.')
            ->set('startDatetime', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('durationMinutes', 45)
            ->call('createSession')
            ->assertHasNoErrors();

        $this->assertSame(
            (int) $room->capacity,
            (int) Session::where('name', 'Clase con aforo de sala')->value('capacity')
        );
    }

    public function test_el_estado_se_deriva_de_la_fecha_y_no_del_formulario()
    {
        $component = Livewire::test(ClassManagement::class)->call('openCreateModal');

        // Futura -> programada
        $this->fill($component, $this->formData([
            'sessionName' => 'Clase futura',
            'startDatetime' => now()->addWeeks(2)->format('Y-m-d\TH:i'),
        ]))->call('createSession')->assertHasNoErrors();

        $this->assertSame(
            Session::STATUS_SCHEDULED,
            Session::where('name', 'Clase futura')->value('status')
        );

        // Pasada -> completada
        $component->call('openCreateModal');
        $this->fill($component, $this->formData([
            'sessionName' => 'Clase pasada',
            'startDatetime' => now()->subWeeks(2)->format('Y-m-d\TH:i'),
        ]))->call('createSession')->assertHasNoErrors();

        $this->assertSame(
            Session::STATUS_COMPLETED,
            Session::where('name', 'Clase pasada')->value('status')
        );
    }

    public function test_un_nombre_demasiado_largo_da_error_de_validacion_y_no_revienta()
    {
        $component = Livewire::test(ClassManagement::class)->call('openCreateModal');

        $this->fill($component, $this->formData([
            'sessionName' => str_repeat('a', 101),
        ]))->call('createSession');

        $component->assertHasErrors(['sessionName' => 'max']);
        $this->assertDatabaseMissing('sessions', ['name' => str_repeat('a', 101)]);
    }

    public function test_la_descripcion_es_obligatoria()
    {
        $component = Livewire::test(ClassManagement::class)->call('openCreateModal');

        $this->fill($component, $this->formData(['sessionDescription' => '']))
            ->call('createSession');

        $component->assertHasErrors(['sessionDescription' => 'required']);
    }

    public function test_no_se_puede_eliminar_una_clase_con_reservas_activas()
    {
        $session = Session::whereDate('date', '>', now())->firstOrFail();

        ClientSession::updateOrCreate(
            ['client_id' => Client::value('id'), 'session_id' => $session->id],
            ['status' => ClientSession::STATUS_RESERVED, 'attendance' => false, 'reserved_at' => now()]
        );

        Livewire::test(ClassManagement::class)
            ->call('confirmDelete', $session->id)
            ->assertSet('reservationsAtRisk', ClientSession::forSession($session->id)->active()->count())
            ->call('deleteSession');

        $this->assertDatabaseHas('sessions', ['id' => $session->id]);
    }

    public function test_se_puede_eliminar_una_clase_sin_reservas()
    {
        $session = Session::create([
            'trainer_id' => Trainer::value('id'),
            'room_id' => Room::value('id'),
            'class_type_id' => ClassType::value('id'),
            'name' => 'Clase sin reservas',
            'description' => 'Se puede borrar.',
            'capacity' => 10,
            'date' => now()->addWeek()->format('Y-m-d'),
            'start_datetime' => '10:00',
            'duration_minutes' => 60,
            'status' => Session::STATUS_SCHEDULED,
        ]);

        Livewire::test(ClassManagement::class)
            ->call('confirmDelete', $session->id)
            ->assertSet('reservationsAtRisk', 0)
            ->call('deleteSession');

        $this->assertDatabaseMissing('sessions', ['id' => $session->id]);
    }

    public function test_cancelar_una_clase_cancela_tambien_sus_reservas()
    {
        $session = Session::whereDate('date', '>', now())->firstOrFail();

        ClientSession::updateOrCreate(
            ['client_id' => Client::value('id'), 'session_id' => $session->id],
            ['status' => ClientSession::STATUS_RESERVED, 'attendance' => false, 'reserved_at' => now()]
        );

        Livewire::test(ClassManagement::class)->call('cancelClass', $session->id);

        $this->assertSame(Session::STATUS_CANCELLED, $session->fresh()->status);
        $this->assertSame(0, ClientSession::forSession($session->id)->active()->count());

        $cancelled = ClientSession::forSession($session->id)
            ->where('status', ClientSession::STATUS_CANCELLED)
            ->first();

        $this->assertNotNull($cancelled->cancelled_at);
    }
}
