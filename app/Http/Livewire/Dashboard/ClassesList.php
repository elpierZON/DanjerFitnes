<?php

namespace App\Http\Livewire\Dashboard;

use Livewire\Component;
use App\Exceptions\ReservationException;
use App\Models\ClassType;
use App\Models\ClientSession;
use App\Models\Session;
use App\Services\ReservationService;
use Carbon\Carbon;

class ClassesList extends Component
{
    public $view = 'types'; // 'types' o 'sessions'
    public $selectedClassType = null;
    public $classTypes = [];
    public $sessions = [];
    public $userReservations = [];

    protected $listeners = ['reservationUpdated' => 'refreshReservations'];

    public function refreshReservations()
    {
        $this->loadSessions();
        $this->loadUserReservations();
    }

    public function mount()
    {
        $this->loadClassTypes();
    }

    public function loadClassTypes()
    {
        $this->classTypes = ClassType::withCount([
            'sessions' => fn($query) => $query->bookable(),
        ])->get();
    }

    public function selectClassType($classTypeId)
    {
        $this->selectedClassType = ClassType::findOrFail($classTypeId);
        $this->view = 'sessions';
        $this->loadSessions();
        $this->loadUserReservations();
    }

    public function backToTypes()
    {
        $this->view = 'types';
        $this->selectedClassType = null;
        $this->sessions = [];
        $this->loadClassTypes();
        
    }

    public function loadSessions()
    {
        if (!$this->selectedClassType) {
            return;
        }
        
        $this->sessions = Session::with(['trainer.user', 'room', 'classType'])
            ->withCount(['reservations as taken_spots' => fn($query) => $query->active()])
            ->where('class_type_id', $this->selectedClassType->id)
            ->bookable()
            ->orderBy('date')
            ->orderBy('start_datetime')
            ->get();
    }

    public function loadUserReservations()
    {   
        
        $client = auth()->user()->client ?? null;

        if ($client) {
            $this->userReservations = $client->activeSessions()
                ->pluck('sessions.id')
                ->toArray();
        }
    }

    public function reserveClass($sessionId, ReservationService $reservations)
    {
        $this->applyReservationChange(
            fn($client, $session) => $reservations->reserve($client, $session),
            $sessionId,
            '¡Clase reservada exitosamente!'
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

    /**
     * Ejecuta una transición de estado contra la base de datos y refresca la vista.
     */
    private function applyReservationChange(callable $change, $sessionId, string $successMessage)
    {
        $client = auth()->user()->client;

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

        $this->refreshReservations();

        // A los demás componentes, no a este (ya se acaba de recargar).
        $this->emitTo('dashboard.stats', 'reservationUpdated');
        $this->emitTo('dashboard.my-reservations', 'reservationUpdated');
    }

    public function render()
    {
        return view('livewire.dashboard.classes-list');
    }
}
