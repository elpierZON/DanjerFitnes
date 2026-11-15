<?php

namespace Tests\Feature;

use App\Http\Livewire\Admin\UserRoles;
use App\Models\ClassType;
use App\Models\Client;
use App\Models\ClientSession;
use App\Models\Session;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cambiar el rol de un usuario no debe destruir sus datos: las tablas de rol
 * usan borrado lógico, así que un cliente conserva sus reservas y un entrenador
 * sus clases aunque se le quite el rol y se le devuelva.
 */
class UserRoleChangeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp() : void
    {
        parent::setUp();

        $admin = User::where('email', 'admin@gym.com')->first();

        if (!$admin) {
            $this->markTestSkipped('Ejecuta "php artisan db:seed" antes de correr estas pruebas.');
        }

        $this->actingAs($admin);
    }

    private function changeRole(User $user, string $role) : void
    {
        $component = Livewire::test(UserRoles::class)
            ->call('confirmRoleChange', $user->id, $role);

        if ($role === 'trainer') {
            $component->call('selectSpeciality', ClassType::first()->id);
        }

        $component->call('changeUserRole');
    }

    private function roleOf(User $user) : string
    {
        return Livewire::test(UserRoles::class)->instance()->getUserRole($user->id);
    }

    public function test_un_cliente_conserva_sus_reservas_al_cambiar_de_rol_y_volver()
    {
        $user = User::where('email', 'cliente@gym.com')->firstOrFail();
        $clientId = Client::where('user_id', $user->id)->value('id');
        $before = ClientSession::where('client_id', $clientId)->count();

        $this->assertGreaterThan(0, $before, 'El cliente de prueba deberia tener reservas.');

        $this->changeRole($user, 'trainer');
        $this->assertSame('trainer', $this->roleOf($user));
        $this->assertSame($before, ClientSession::where('client_id', $clientId)->count());

        $this->changeRole($user, 'client');
        $this->assertSame('client', $this->roleOf($user));

        // Se reactiva la MISMA fila, no una nueva
        $this->assertSame($clientId, Client::where('user_id', $user->id)->value('id'));
        $this->assertSame($before, ClientSession::where('client_id', $clientId)->count());
    }

    public function test_un_entrenador_conserva_sus_clases_al_cambiar_de_rol_y_volver()
    {
        $trainerId = DB::table('sessions')
            ->selectRaw('trainer_id, count(*) t')
            ->groupBy('trainer_id')
            ->orderByDesc('t')
            ->value('trainer_id');

        $user = Trainer::findOrFail($trainerId)->user;
        $before = Session::where('trainer_id', $trainerId)->count();

        $this->assertGreaterThan(0, $before);

        $this->changeRole($user, 'administrator');
        $this->assertSame('administrator', $this->roleOf($user));

        // Las clases (y las reservas de otros clientes en ellas) siguen ahí
        $this->assertSame($before, Session::where('trainer_id', $trainerId)->count());

        $this->changeRole($user, 'trainer');
        $this->assertSame('trainer', $this->roleOf($user));
        $this->assertSame($trainerId, Trainer::where('user_id', $user->id)->value('id'));
        $this->assertSame($before, Session::where('trainer_id', $trainerId)->count());
    }

    public function test_reasignar_el_mismo_rol_no_toca_los_datos()
    {
        $user = User::where('email', 'cliente@gym.com')->firstOrFail();
        $clientId = Client::where('user_id', $user->id)->value('id');
        $before = ClientSession::where('client_id', $clientId)->count();

        $this->changeRole($user, 'client');

        $this->assertSame($clientId, Client::where('user_id', $user->id)->value('id'));
        $this->assertSame($before, ClientSession::where('client_id', $clientId)->count());
    }

    public function test_el_usuario_solo_tiene_un_rol_activo_a_la_vez()
    {
        $user = User::where('email', 'cliente@gym.com')->firstOrFail();

        $this->changeRole($user, 'trainer');

        $user->refresh();
        $this->assertNotNull($user->trainer);
        $this->assertNull($user->client, 'El rol de cliente debe quedar inactivo, no visible.');
        $this->assertNull($user->administrator);
    }
}
