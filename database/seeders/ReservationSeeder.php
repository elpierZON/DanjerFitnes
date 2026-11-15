<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\ClientSession;
use App\Models\Session;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Genera reservas (client_sessions) con estados realistas:
 * las clases ya dictadas quedan completadas o canceladas, y las próximas
 * reservadas, confirmadas o canceladas.
 */
class ReservationSeeder extends Seeder
{
    /** Cliente de prueba que recibe un historial completo y variado. */
    const DEMO_CLIENT_EMAIL = 'cliente@gym.com';

    /** Filas indexadas por "clientId-sessionId" para no duplicar reservas. */
    private array $rows = [];

    /** Pares cliente-sesión que ya están en la base de datos. */
    private array $existing = [];

    public function run()
    {
        $clients = Client::with('user')->get();
        $sessions = Session::orderBy('date')->get();

        if ($clients->isEmpty() || $sessions->isEmpty()) {
            $this->command->warn('No hay clientes o sesiones. Ejecuta primero los seeders base.');
            return;
        }

        // Permite re-ejecutar el seeder para rellenar solo lo que falte.
        $this->existing = DB::table('client_sessions')
            ->select('client_id', 'session_id')
            ->get()
            ->mapWithKeys(fn($r) => [$r->client_id.'-'.$r->session_id => true])
            ->all();

        if ($this->existing) {
            $this->command->info('Ya hay '.count($this->existing).' reservas; se añadirán solo las que falten.');
        }

        $demoClient = $clients->first(
            fn($client) => optional($client->user)->email === self::DEMO_CLIENT_EMAIL
        );

        if ($demoClient) {
            $this->seedDemoClient($demoClient, $sessions);
        }

        $this->seedEveryone($clients, $sessions);

        foreach (array_chunk(array_values($this->rows), 200) as $chunk) {
            DB::table('client_sessions')->insert($chunk);
        }

        $this->reportTotals();
    }

    /**
     * Historial garantizado para el cliente de prueba: al menos varias reservas
     * de cada estado, para poder revisarlas en el dashboard.
     */
    private function seedDemoClient(Client $client, $sessions)
    {
        $past = $sessions->filter(fn($s) => $s->date->isPast() && !$s->date->isToday())->shuffle();
        $upcoming = $sessions->filter(fn($s) => $s->date->isFuture())->shuffle();

        $plan = [
            [$past, 6, ClientSession::STATUS_COMPLETED],
            [$past, 2, ClientSession::STATUS_CANCELLED],
            [$upcoming, 4, ClientSession::STATUS_RESERVED],
            [$upcoming, 3, ClientSession::STATUS_CONFIRMED],
            [$upcoming, 2, ClientSession::STATUS_CANCELLED],
        ];

        foreach ($plan as [$pool, $amount, $status]) {
            $taken = 0;

            foreach ($pool as $session) {
                if ($taken >= $amount) break;
                if ($this->alreadyTaken($client, $session)) continue;

                $this->addReservation($client, $session, $status);
                $taken++;
            }
        }
    }

    /**
     * Reservas repartidas entre todos los clientes, respetando el aforo de cada sesión.
     */
    private function seedEveryone($clients, $sessions)
    {
        foreach ($sessions as $session) {
            $howMany = min(
                $clients->count(),
                (int) $session->capacity,
                rand(3, 8)
            );

            foreach ($clients->random($howMany) as $client) {
                if ($this->alreadyTaken($client, $session)) continue;

                $this->addReservation($client, $session, $this->statusFor($session));
            }
        }
    }

    /**
     * Estado plausible según cuándo se dicta la clase.
     */
    private function statusFor(Session $session) : string
    {
        if ($session->date->isToday()) {
            return collect([
                ClientSession::STATUS_CONFIRMED,
                ClientSession::STATUS_CONFIRMED,
                ClientSession::STATUS_RESERVED,
            ])->random();
        }

        if ($session->date->isPast()) {
            // La mayoría se dictó; unas pocas se cancelaron a tiempo.
            return rand(1, 100) <= 80
                ? ClientSession::STATUS_COMPLETED
                : ClientSession::STATUS_CANCELLED;
        }

        return collect(array_merge(
            array_fill(0, 5, ClientSession::STATUS_RESERVED),
            array_fill(0, 3, ClientSession::STATUS_CONFIRMED),
            array_fill(0, 2, ClientSession::STATUS_CANCELLED)
        ))->random();
    }

    private function addReservation(Client $client, Session $session, string $status)
    {
        // Se reserva algunos días antes de la clase (nunca en el futuro).
        $reservedAt = $session->date->copy()
            ->subDays(rand(1, 10))
            ->setTime(rand(7, 21), rand(0, 59));

        if ($reservedAt->isFuture()) {
            $reservedAt = Carbon::now()->subHours(rand(1, 48));
        }

        $cancelledAt = $status === ClientSession::STATUS_CANCELLED
            ? $reservedAt->copy()->addHours(rand(2, 72))
            : null;

        if ($cancelledAt && $cancelledAt->isFuture()) {
            $cancelledAt = Carbon::now()->subHours(rand(1, 12));
        }

        // Solo una clase completada registra asistencia; algunos alumnos faltaron.
        $attendance = $status === ClientSession::STATUS_COMPLETED && rand(1, 100) <= 85;

        $this->rows[$this->key($client, $session)] = [
            'client_id'    => $client->id,
            'session_id'   => $session->id,
            'status'       => $status,
            'attendance'   => $attendance,
            'reserved_at'  => $reservedAt,
            'cancelled_at' => $cancelledAt,
            'created_at'   => $reservedAt,
            'updated_at'   => $cancelledAt ?? $reservedAt,
        ];
    }

    private function key(Client $client, Session $session) : string
    {
        return $client->id . '-' . $session->id;
    }

    /** ¿Ya existe esa reserva, en esta tanda o en la base de datos? */
    private function alreadyTaken(Client $client, Session $session) : bool
    {
        $key = $this->key($client, $session);

        return isset($this->rows[$key]) || isset($this->existing[$key]);
    }

    private function reportTotals()
    {
        $totals = DB::table('client_sessions')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $this->command->info('✅ Reservas creadas: ' . array_sum($totals->toArray()));

        foreach (ClientSession::STATUS_LABELS as $status => $label) {
            $this->command->line("   {$label}s: " . ($totals[$status] ?? 0));
        }
    }
}
