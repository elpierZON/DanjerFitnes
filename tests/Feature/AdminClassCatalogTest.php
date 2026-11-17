<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\ClassCatalog;
use App\Http\Livewire\Admin\ClassManagement;
use App\Models\ClassType;
use App\Models\Room;
use App\Models\Session;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Módulo «Tipos y Salas»: el admin registra tipos de clase y salas para
 * poder usarlos al crear una clase nueva.
 */
class AdminClassCatalogTest extends TestCase
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

    public function test_el_panel_muestra_el_modulo_tipos_y_salas()
    {
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Tipos y Salas', false)
            ->assertSee('Tipos de Clase y Salas', false);
    }

    public function test_crear_un_tipo_de_clase()
    {
        Livewire::test(ClassCatalog::class)
            ->call('openTypeModal')
            ->set('typeName', 'Yoga')
            ->set('typeDescription', 'Posturas, respiración y flexibilidad.')
            ->call('saveType')
            ->assertHasNoErrors()
            ->assertSet('showTypeModal', false);

        $this->assertDatabaseHas('class_types', ['name' => 'Yoga', 'status' => 1]);
    }

    public function test_no_permite_tipos_duplicados_ni_vacios()
    {
        $existing = ClassType::firstOrFail();

        Livewire::test(ClassCatalog::class)
            ->call('openTypeModal')
            ->set('typeName', $existing->name)
            ->set('typeDescription', '')
            ->call('saveType')
            ->assertHasErrors(['typeName' => 'unique', 'typeDescription' => 'required']);
    }

    public function test_crear_y_editar_una_sala()
    {
        $component = Livewire::test(ClassCatalog::class)
            ->call('setTab', 'rooms')
            ->call('openRoomModal')
            ->set('roomName', 'Sala de Yoga')
            ->set('roomCapacity', 12)
            ->set('roomDescription', 'Sala tranquila con colchonetas.')
            ->call('saveRoom')
            ->assertHasNoErrors();

        $room = Room::where('name', 'Sala de Yoga')->firstOrFail();
        $this->assertSame(12, (int) $room->capacity);

        $component->call('openRoomModal', $room->id)
            ->assertSet('roomName', 'Sala de Yoga')
            ->set('roomCapacity', 18)
            ->call('saveRoom')
            ->assertHasNoErrors();

        $this->assertSame(18, (int) $room->fresh()->capacity);
    }

    public function test_el_aforo_de_la_sala_debe_ser_positivo()
    {
        Livewire::test(ClassCatalog::class)
            ->call('openRoomModal')
            ->set('roomName', 'Sala sin aforo')
            ->set('roomCapacity', 0)
            ->set('roomDescription', 'x')
            ->call('saveRoom')
            ->assertHasErrors(['roomCapacity' => 'min']);
    }

    public function test_no_borra_una_sala_con_clases()
    {
        $room = Room::has('sessions')->firstOrFail();
        $sessions = $room->sessions()->count();

        Livewire::test(ClassCatalog::class)
            ->call('confirmDelete', 'room', $room->id)
            ->assertSet('deleteSessions', $sessions)
            ->call('delete');

        $this->assertNotNull($room->fresh());
        $this->assertSame($sessions, $room->sessions()->count());
    }

    public function test_borra_una_sala_sin_clases()
    {
        $room = Room::create(['name' => 'Sala temporal', 'capacity' => 5, 'description' => 'Para borrar.']);

        Livewire::test(ClassCatalog::class)
            ->call('confirmDelete', 'room', $room->id)
            ->call('delete');

        $this->assertNull($room->fresh());
    }

    public function test_un_tipo_con_clases_se_desactiva_en_vez_de_borrarse()
    {
        $type = ClassType::has('sessions')->firstOrFail();

        Livewire::test(ClassCatalog::class)
            ->call('confirmDelete', 'type', $type->id)
            ->call('deactivateInsteadOfDelete');

        $this->assertFalse($type->fresh()->status);
        $this->assertTrue($type->sessions()->exists());
    }

    public function test_la_clase_nueva_puede_usar_el_tipo_y_la_sala_recien_creados()
    {
        $type = ClassType::create(['name' => 'Pilates', 'description' => 'Control y core.', 'status' => true]);
        $room = Room::create(['name' => 'Sala Pilates', 'capacity' => 8, 'description' => 'Con reformers.']);

        Livewire::test(ClassManagement::class)
            ->call('openCreateModal')
            ->assertSee('Pilates')
            ->assertSee('Sala Pilates')
            ->set('trainerId', Trainer::value('id'))
            ->set('roomId', $room->id)
            ->set('classTypeId', $type->id)
            ->set('sessionName', 'Pilates mañana')
            ->set('sessionDescription', 'Clase de prueba.')
            ->set('startDatetime', now()->addWeek()->format('Y-m-d\TH:i'))
            ->set('durationMinutes', 50)
            ->call('createSession')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sessions', [
            'name' => 'Pilates mañana',
            'class_type_id' => $type->id,
            'room_id' => $room->id,
            'capacity' => 8,
        ]);
    }

    public function test_un_tipo_inactivo_no_se_ofrece_para_clases_nuevas()
    {
        $type = ClassType::create(['name' => 'Spinning viejo', 'description' => 'Retirado.', 'status' => false]);

        Livewire::test(ClassManagement::class)
            ->call('openCreateModal')
            ->assertDontSee('Spinning viejo')
            ->set('classTypeId', $type->id)
            ->call('createSession')
            ->assertHasErrors(['classTypeId']);
    }

    public function test_editar_una_clase_conserva_su_tipo_aunque_este_inactivo()
    {
        $session = Session::whereDate('date', '>', now())->firstOrFail();
        $session->classType->update(['status' => false]);

        Livewire::test(ClassManagement::class)
            ->call('editSession', $session->id)
            ->assertSee($session->classType->name)
            ->set('sessionName', 'Renombrada')
            ->call('updateSession')
            ->assertHasNoErrors(['classTypeId']);
    }
}
