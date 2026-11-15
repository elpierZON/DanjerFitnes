<?php

namespace Database\Seeders;

use App\Models\Administrator;
use App\Models\Client;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Room;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $plans = $this->createPlans();
        $this->createRooms();

        // ========================================
        // USUARIOS DE PRUEBA (uno por rol)
        // ========================================
        Trainer::create([
            'user_id' => $this->createUser('Carlos', 'Ramírez', 'entrenador@gym.com', '987654321', '12345678')->id,
            'speciality' => 'HIIT',
            'years_of_experience' => 8,
        ]);

        $mainClient = Client::create([
            'user_id' => $this->createUser('Lucía', 'Fernández', 'cliente@gym.com', '987123456', '87654321')->id,
            'address' => 'Av. Principal 123',
            'emergency_phone' => 987000111,
        ]);

        Administrator::create([
            'user_id' => $this->createUser('Ana', 'Torres', 'admin@gym.com', '987000222', '11223344')->id,
        ]);

        // ========================================
        // OTROS USUARIOS (ficticios)
        // ========================================
        User::factory(2)->create()->each(fn($user) => Administrator::factory()->create([
            'user_id' => $user->id,
        ]));

        User::factory(5)->create()->each(fn($user) => Trainer::factory()->create([
            'user_id' => $user->id,
        ]));

        $clients = User::factory(15)->create()->map(fn($user) => Client::factory()->create([
            'user_id' => $user->id,
        ]));

        $clients->prepend($mainClient);

        // Sesiones reales (3 semanas pasadas, la actual y 3 futuras)
        $this->call(SessionSeeder::class);

        // Reservas con estados persistidos en client_sessions
        $this->call(ReservationSeeder::class);

        // ========================================
        // MEMBRESÍAS Y PAGOS
        // ========================================
        $memberships = collect();

        foreach ($clients as $client) {
            // Una membresía activa vigente por cliente + alguna vencida ocasional.
            $memberships->push(Membership::create([
                'client_id' => $client->id,
                'plan_id' => $plans->random()->id,
                'status' => 1,
            ]));

            if (rand(1, 100) <= 40) {
                $memberships->push(Membership::create([
                    'client_id' => $client->id,
                    'plan_id' => $plans->random()->id,
                    'status' => 0,
                ]));
            }
        }

        $memberships->each(fn($membership) => Payment::factory(rand(1, 3))->create([
            'membership_id' => $membership->id,
        ]));

        $this->printCredentials();
    }

    private function createUser(string $name, string $lastname, string $email, string $phone, string $dni) : User
    {
        return User::create([
            'name' => $name,
            'lastname' => $lastname,
            'email' => $email,
            'password' => bcrypt('password123'),
            'phone' => $phone,
            'dni' => $dni,
        ]);
    }

    private function createPlans()
    {
        $plans = [
            ['type' => 'Mensual', 'price' => 89.90, 'description' => 'Acceso ilimitado a todas las clases durante un mes.'],
            ['type' => 'Mensual', 'price' => 59.90, 'description' => 'Plan básico mensual con acceso a 12 clases.'],
            ['type' => 'Anual', 'price' => 899.00, 'description' => 'Acceso ilimitado durante un año con dos meses de regalo.'],
            ['type' => 'Anual', 'price' => 649.00, 'description' => 'Plan anual básico con acceso a 150 clases.'],
        ];

        return collect($plans)->map(fn($plan) => Plan::create($plan));
    }

    private function createRooms()
    {
        $rooms = [
            ['name' => 'Sala Cardio', 'capacity' => 30, 'description' => 'Sala amplia con espejos y equipo de sonido para clases grupales.'],
            ['name' => 'Sala Funcional', 'capacity' => 20, 'description' => 'Espacio con material funcional: kettlebells, cuerdas y cajones.'],
            ['name' => 'Sala de Pesas', 'capacity' => 15, 'description' => 'Zona de máquinas y peso libre para entrenamiento de fuerza.'],
        ];

        return collect($rooms)->map(fn($room) => Room::create($room));
    }

    private function printCredentials()
    {
        $this->command->line('');
        $this->command->info('✅ Base de datos poblada. Usuarios de prueba (password: password123):');
        $this->command->line('   Cliente:     cliente@gym.com');
        $this->command->line('   Entrenador:  entrenador@gym.com');
        $this->command->line('   Admin:       admin@gym.com');
    }
}
