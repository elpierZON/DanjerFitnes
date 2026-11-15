<?php

namespace App\Http\Livewire\Dashboard;

use App\Exceptions\ReservationException;
use App\Models\ClientSession;
use App\Models\Session;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MyReservations extends Component
{
    const PER_PAGE = 12;

    public $filter = 'all'; // all, reserved, confirmed, completed, cancelled
    public $reservations = [];
    public $counts = [];
    public $perPage = self::PER_PAGE;
    public $hasMore = false;

    protected $listeners = ['reservationUpdated' => 'loadReservations'];

    public function mount()
    {
        $this->loadReservations();
    }

    public function loadReservations()
    {
        $client = Auth::user()->client ?? null;

        if (!$client) {
            $this->reservations = [];
            $this->counts = [];
            $this->hasMore = false;
            return;
        }

        $query = $client->sessions()
            ->with(['trainer.user', 'room', 'classType'])
            ->orderByDesc('sessions.date')
            ->orderByDesc('sessions.start_datetime');

        if ($this->filter !== 'all') {
            $query->wherePivot('status', $this->filter);
        }

        // Se pide uno de más para saber si quedan reservas por cargar.
        $page = $query->limit($this->perPage + 1)->get();

        $this->hasMore = $page->count() > $this->perPage;
        $this->reservations = $page->take($this->perPage);

        $this->counts = ['all' => $client->reservations()->count()]
            + $client->reservations()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();
    }

    public function setFilter($filter)
    {
        $this->filter = $filter;
        $this->perPage = self::PER_PAGE;
        $this->loadReservations();
    }

    public function loadMore()
    {
        $this->perPage += self::PER_PAGE;
        $this->loadReservations();
    }

    public function confirmReservation($sessionId, ReservationService $reservations)
    {
        $this->applyReservationChange(
            fn($client, $session) => $reservations->confirm($client, $session),
            $sessionId,
            '¡Asistencia confirmada!'
        );
    }

    public function cancelReservation($sessionId, ReservationService $reservations)
    {
        $this->applyReservationChange(
            fn($client, $session) => $reservations->cancel($client, $session),
            $sessionId,
            'Reserva cancelada exitosamente.'
        );
    }

    public function reactivateReservation($sessionId, ReservationService $reservations)
    {
        $this->applyReservationChange(
            fn($client, $session) => $reservations->reserve($client, $session),
            $sessionId,
            '¡Reserva reactivada exitosamente!'
        );
    }

    /**
     * Ejecuta la transición contra la base de datos y refresca la lista.
     */
    private function applyReservationChange(callable $change, $sessionId, string $successMessage)
    {
        $client = Auth::user()->client;

        if (!$client) {
            session()->flash('error', 'No tienes un perfil de cliente asociado.');
            return;
        }

        try {
            $change($client, Session::findOrFail($sessionId));
            session()->flash('message', $successMessage);
        } catch (ReservationException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->loadReservations();

        // A los demás componentes, no a este (ya se acaba de recargar).
        $this->emitTo('dashboard.stats', 'reservationUpdated');
        $this->emitTo('dashboard.classes-list', 'reservationUpdated');
    }

    public function render()
    {
        return view('livewire.dashboard.my-reservations', [
            'statusLabels' => ClientSession::STATUS_LABELS,
            'today'        => Carbon::today(),
        ]);
    }
}
