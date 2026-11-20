<?php

namespace App\Http\Livewire\Trainer;

use App\Exceptions\ReservationException;
use App\Models\ClassType;
use App\Models\ClientSession;
use App\Models\Session;
use App\Models\Trainer;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * "Mis Clases" del entrenador: primero elige un tipo de clase y dentro ve,
 * filtra y busca las clases de ese tipo.
 *
 * Las clases no se guardan en propiedades públicas: Livewire 2 las enviaría al
 * navegador y las volvería a consultar (con todos sus alumnos) en cada clic.
 * Se calculan en render() y solo viajan los ids y los filtros.
 */
class MyClasses extends Component
{
    const FILTERS = ['upcoming', 'today', 'past', 'all'];

    public $view = 'types'; // types | sessions
    public $classTypeId = null;
    public $filter = 'upcoming';
    public $search = '';
    public $selectedClassId = null;
    public $showStudentsModal = false;

    public function selectClassType($classTypeId)
    {
        $trainer = $this->trainer();

        // Solo los tipos que el entrenador dicta: así no se abre una lista vacía
        // de un tipo ajeno.
        if (!$trainer || !$trainer->sessions()->where('class_type_id', $classTypeId)->exists()) {
            session()->flash('error', 'No tienes clases de ese tipo.');
            return;
        }

        $this->classTypeId = (int) $classTypeId;
        $this->view = 'sessions';
        $this->filter = 'upcoming';
        $this->search = '';
    }

    public function backToTypes()
    {
        $this->closeModal();
        $this->view = 'types';
        $this->classTypeId = null;
        $this->search = '';
    }

    public function setFilter($filter)
    {
        if (in_array($filter, self::FILTERS, true)) {
            $this->filter = $filter;
        }
    }

    public function viewStudents($sessionId)
    {
        if (!$this->ownedSession($sessionId)) {
            session()->flash('error', 'Esa clase no existe o no es tuya.');
            return;
        }

        $this->selectedClassId = (int) $sessionId;
        $this->showStudentsModal = true;
    }

    public function closeModal()
    {
        $this->showStudentsModal = false;
        $this->selectedClassId = null;
    }

    /**
     * Pasar lista: marcar asistencia completa la reserva en la base de datos,
     * desmarcarla la devuelve a confirmada.
     */
    public function toggleAttendance($sessionId, $clientId, ReservationService $reservations)
    {
        $session = $this->ownedSession($sessionId);

        if (!$session) {
            session()->flash('error', 'Esa clase no existe o no es tuya.');
            return;
        }

        $client = $session->clients()->where('clients.id', $clientId)->first();

        if (!$client) {
            session()->flash('error', 'Este alumno no tiene reserva en la clase.');
            return;
        }

        try {
            $reservations->markAttendance($session, $client, !$client->pivot->attendance);
            session()->flash('message', 'Asistencia actualizada correctamente.');
        } catch (ReservationException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->emit('classUpdated');
    }

    public function render()
    {
        $inSessions = $this->view === 'sessions';

        return view('livewire.trainer.my-classes', [
            'classTypes'    => $inSessions ? collect() : $this->classTypes(),
            'classType'     => $inSessions ? ClassType::find($this->classTypeId) : null,
            'classes'       => $inSessions ? $this->classes() : collect(),
            'selectedClass' => $this->showStudentsModal ? $this->ownedSession($this->selectedClassId) : null,
        ]);
    }

    private function trainer() : ?Trainer
    {
        return Auth::user()->trainer;
    }

    /**
     * Busca la clase solo entre las del entrenador autenticado. El id llega desde
     * el navegador, así que sin este filtro cualquier entrenador podría ver o
     * modificar las clases de otro.
     */
    private function ownedSession($sessionId) : ?Session
    {
        $trainer = $this->trainer();

        if (!$trainer || !$sessionId) {
            return null;
        }

        return $trainer->sessions()
            ->with(['clients.user', 'classType', 'room'])
            ->find($sessionId);
    }

    /** Tipos de clase que dicta el entrenador, con cuántas clases tiene de cada uno. */
    private function classTypes() : Collection
    {
        $trainer = $this->trainer();

        if (!$trainer) {
            return collect();
        }

        $mine = fn($query) => $query->where('trainer_id', $trainer->id);

        return ClassType::whereHas('sessions', $mine)
            ->withCount([
                'sessions as total_classes' => $mine,
                'sessions as upcoming_classes' => fn($query) => $this->applyFilter($mine($query), 'upcoming'),
                'sessions as today_classes' => fn($query) => $this->applyFilter($mine($query), 'today'),
            ])
            ->orderBy('name')
            ->get();
    }

    /** Clases del tipo elegido, con el filtro de fecha y la búsqueda aplicados. */
    private function classes() : Collection
    {
        $trainer = $this->trainer();

        if (!$trainer || !$this->classTypeId) {
            return collect();
        }

        $query = $trainer->sessions()
            ->where('class_type_id', $this->classTypeId)
            ->with(['room', 'classType'])
            ->withCount(['reservations as enrolled' => fn($q) => $q->where('status', '!=', ClientSession::STATUS_CANCELLED)]);

        $this->applyFilter($query, $this->filter);
        $this->applySearch($query, trim((string) $this->search));

        // Las pasadas, de la más reciente a la más antigua; el resto, en orden de llegada.
        $direction = $this->filter === 'past' ? 'desc' : 'asc';

        return $query->orderBy('date', $direction)
            ->orderBy('start_datetime', $direction)
            ->get();
    }

    /**
     * @param Builder|Relation $query
     * @return Builder|Relation
     */
    private function applyFilter($query, string $filter)
    {
        switch ($filter) {
            case 'upcoming':
                return $query->where('date', '>', Carbon::today()->toDateString())
                    ->where('status', Session::STATUS_SCHEDULED);
            case 'today':
                return $query->whereDate('date', Carbon::today());
            case 'past':
                return $query->where('date', '<', Carbon::today()->toDateString());
            default:
                return $query;
        }
    }

    /**
     * Busca por nombre, descripción, sala o fecha (dd/mm/aaaa, también parcial).
     *
     * @param Builder|Relation $query
     */
    private function applySearch($query, string $term) : void
    {
        if ($term === '') {
            return;
        }

        $like = '%' . addcslashes($term, '\\%_') . '%';

        $query->where(function ($q) use ($like) {
            $q->where('sessions.name', 'like', $like)
                ->orWhere('sessions.description', 'like', $like)
                ->orWhereRaw("DATE_FORMAT(sessions.date, '%d/%m/%Y') like ?", [$like])
                ->orWhereHas('room', fn($room) => $room->where('name', 'like', $like));
        });
    }
}
