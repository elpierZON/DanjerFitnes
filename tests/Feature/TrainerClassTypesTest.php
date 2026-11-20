<?php

namespace Tests\Feature;

use App\Http\Livewire\Trainer\MyClasses;
use App\Models\ClassType;
use App\Models\Session;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Mis Clases" del entrenador se navega por tipo de clase y, dentro de cada
 * tipo, se puede buscar.
 */
class TrainerClassTypesTest extends TestCase
{
    use DatabaseTransactions;

    private Trainer $trainer;

    protected function setUp() : void
    {
        parent::setUp();

        $user = User::where('email', 'entrenador@gym.com')->first();

        if (!$user || !$user->trainer || !$user->trainer->sessions()->exists()) {
            $this->markTestSkipped('Ejecuta "php artisan db:seed" antes de correr estas pruebas.');
        }

        $this->trainer = $user->trainer;
        $this->actingAs($user);
    }

    private function someTypeOfMine() : ClassType
    {
        return ClassType::whereHas('sessions', fn($q) => $q->where('trainer_id', $this->trainer->id))->firstOrFail();
    }

    public function test_la_vista_inicial_muestra_solo_los_tipos_que_dicta_el_entrenador()
    {
        $ajeno = ClassType::create(['name' => 'Tipo sin clases del entrenador', 'description' => 'x', 'status' => 1]);

        $types = Livewire::test(MyClasses::class)
            ->assertSet('view', 'types')
            ->viewData('classTypes');

        $expected = ClassType::whereHas('sessions', fn($q) => $q->where('trainer_id', $this->trainer->id))->pluck('id');

        $this->assertEqualsCanonicalizing($expected->all(), $types->pluck('id')->all());
        $this->assertNotContains($ajeno->id, $types->pluck('id')->all());

        $first = $types->first();
        $this->assertSame(
            $this->trainer->sessions()->where('class_type_id', $first->id)->count(),
            (int) $first->total_classes
        );
    }

    public function test_al_elegir_un_tipo_solo_se_ven_sus_clases()
    {
        $type = $this->someTypeOfMine();

        $classes = Livewire::test(MyClasses::class)
            ->call('selectClassType', $type->id)
            ->assertSet('view', 'sessions')
            ->call('setFilter', 'all')
            ->viewData('classes');

        $this->assertNotEmpty($classes);
        $this->assertSame(
            $this->trainer->sessions()->where('class_type_id', $type->id)->count(),
            $classes->count()
        );
        $this->assertTrue($classes->every(fn($c) => $c->class_type_id === $type->id && $c->trainer_id === $this->trainer->id));
    }

    public function test_no_se_puede_abrir_un_tipo_que_el_entrenador_no_dicta()
    {
        $ajeno = ClassType::create(['name' => 'Tipo ajeno', 'description' => 'x', 'status' => 1]);

        Livewire::test(MyClasses::class)
            ->call('selectClassType', $ajeno->id)
            ->assertSet('view', 'types')
            ->assertSet('classTypeId', null);
    }

    public function test_el_buscador_filtra_por_nombre_dentro_del_tipo()
    {
        $type = $this->someTypeOfMine();
        $target = $this->trainer->sessions()->where('class_type_id', $type->id)->firstOrFail();
        $target->update(['name' => 'Clase buscable zxq']);

        $component = Livewire::test(MyClasses::class)
            ->call('selectClassType', $type->id)
            ->call('setFilter', 'all')
            ->set('search', 'zxq');

        $classes = $component->viewData('classes');
        $this->assertSame([$target->id], $classes->pluck('id')->all());

        $component->set('search', 'texto-que-no-existe-123')
            ->assertSee('Ninguna clase coincide');
    }

    public function test_el_buscador_encuentra_por_fecha()
    {
        $type = $this->someTypeOfMine();
        $target = $this->trainer->sessions()->where('class_type_id', $type->id)->firstOrFail();

        $classes = Livewire::test(MyClasses::class)
            ->call('selectClassType', $type->id)
            ->call('setFilter', 'all')
            ->set('search', $target->date->format('d/m/Y'))
            ->viewData('classes');

        $this->assertContains($target->id, $classes->pluck('id')->all());
        $this->assertTrue($classes->every(fn($c) => $c->date->isSameDay($target->date)));
    }

    public function test_volver_a_tipos_limpia_la_busqueda()
    {
        $type = $this->someTypeOfMine();

        Livewire::test(MyClasses::class)
            ->call('selectClassType', $type->id)
            ->set('search', 'algo')
            ->call('backToTypes')
            ->assertSet('view', 'types')
            ->assertSet('search', '')
            ->assertSet('classTypeId', null);
    }
}
