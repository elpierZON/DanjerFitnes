<?php

namespace App\Http\Livewire\Admin;

use App\Models\ClassType;
use App\Models\ClientSession;
use App\Models\Room;
use App\Models\Session;
use App\Models\Trainer;
use App\Services\ReservationService;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ClassManagement extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $searchTerm = '';
    public $showCreateModal = false;
    public $confirmingDelete = false;
    public $selectedSessionId = null;

    /** Reservas vigentes de la clase que se quiere borrar. */
    public $reservationsAtRisk = 0;

    // Formulario. La capacidad sale de la sala y el estado de la fecha,
    // así que ninguno de los dos se pide aquí.
    public $trainerId = '';
    public $roomId = '';
    public $classTypeId = '';
    public $sessionName = '';
    public $sessionDescription = '';
    public $startDatetime = '';
    public $durationMinutes = '';

    protected function rules() : array
    {
        // Una clase nueva solo puede usar tipos activos. Al editar se respeta el
        // tipo que ya tenía aunque luego se haya desactivado.
        $classTypeRule = Rule::exists('class_types', 'id');

        if ((string) $this->classTypeId !== (string) $this->originalClassTypeId()) {
            $classTypeRule = $classTypeRule->where('status', true);
        }

        return [
            'trainerId' => 'required|exists:trainers,id',
            'roomId' => 'required|exists:rooms,id',
            'classTypeId' => ['required', $classTypeRule],
            'sessionName' => 'required|string|max:100',
            'sessionDescription' => 'required|string|max:300',
            'startDatetime' => 'required|date_format:Y-m-d\TH:i',
            'durationMinutes' => 'required|numeric|min:1|max:480',
        ];
    }

    protected function validationAttributes() : array
    {
        return [
            'trainerId' => 'entrenador',
            'roomId' => 'sala',
            'classTypeId' => 'tipo de clase',
            'sessionName' => 'nombre',
            'sessionDescription' => 'descripción',
            'startDatetime' => 'fecha y hora',
            'durationMinutes' => 'duración',
        ];
    }

    public function updatingSearchTerm()
    {
        $this->resetPage();
    }

    public function getSessions()
    {
        // Las clases envejecen: lo que ayer estaba programado hoy ya se dictó.
        Session::refreshStatuses();

        $term = trim($this->searchTerm);

        return Session::query()
            ->with(['trainer.user', 'room', 'classType'])
            ->withCount(['reservations as taken_spots' => fn($query) => $query->active()])
            ->when($term !== '', function ($query) use ($term) {
                // Agrupado: si no, cualquier filtro futuro quedaría anulado por los OR.
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhereHas('trainer.user', function ($u) use ($term) {
                            $u->where('name', 'like', "%{$term}%")
                                ->orWhere('lastname', 'like', "%{$term}%");
                        });
                });
            })
            ->orderByDesc('date')
            ->orderBy('start_datetime')
            ->paginate(15);
    }

    public function getTrainers()
    {
        return Trainer::with('user')->get();
    }

    public function getRooms()
    {
        return Room::orderBy('name')->get();
    }

    /** Tipo que tenía la clase en edición (null al crear). */
    private function originalClassTypeId()
    {
        return $this->selectedSessionId
            ? Session::whereKey($this->selectedSessionId)->value('class_type_id')
            : null;
    }

    public function getClassTypes()
    {
        // Los tipos se administran en «Tipos y Salas» (ClassCatalog).
        $keep = $this->originalClassTypeId();

        return ClassType::query()
            ->where(fn($q) => $q->active()->when($keep, fn($q) => $q->orWhere('id', $keep)))
            ->orderBy('name')
            ->get();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    /** Atajo desde el formulario: lleva a «Tipos y Salas» en la pestaña pedida. */
    public function goToCatalog($tab)
    {
        $this->closeCreateModal();
        $this->emitTo('admin.class-catalog', 'showTab', $tab);
        $this->dispatchBrowserEvent('admin-navigate', ['section' => 'catalog']);
    }

    public function closeCreateModal()
    {
        $this->resetForm();
        $this->showCreateModal = false;
    }

    public function resetForm()
    {
        // Limpiar el id es lo que evita que "Nueva Clase" acabe sobrescribiendo
        // la última clase editada.
        $this->selectedSessionId = null;

        $this->trainerId = '';
        $this->roomId = '';
        $this->classTypeId = '';
        $this->sessionName = '';
        $this->sessionDescription = '';
        $this->startDatetime = '';
        $this->durationMinutes = '';

        $this->resetErrorBag();
    }

    public function createSession()
    {
        $this->validate();

        $dateTime = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $this->startDatetime);
        $room = Room::findOrFail($this->roomId);

        $session = new Session([
            'trainer_id' => $this->trainerId,
            'room_id' => $room->id,
            'class_type_id' => $this->classTypeId,
            'name' => $this->sessionName,
            'description' => $this->sessionDescription,
            'capacity' => $room->capacity,
            'date' => $dateTime->format('Y-m-d'),
            'start_datetime' => $dateTime->format('H:i'),
            'duration_minutes' => $this->durationMinutes,
        ]);

        $session->status = Session::statusFor($dateTime, (int) $this->durationMinutes);
        $session->save();

        session()->flash('message', "Clase «{$session->name}» creada correctamente.");
        $this->closeCreateModal();
    }

    public function editSession($sessionId)
    {
        $session = Session::find($sessionId);

        if (!$session) {
            session()->flash('error', 'Esa clase ya no existe.');
            return;
        }

        $this->resetForm();

        $this->selectedSessionId = $session->id;
        $this->trainerId = $session->trainer_id;
        $this->roomId = $session->room_id;
        $this->classTypeId = $session->class_type_id;
        $this->sessionName = $session->name;
        $this->sessionDescription = $session->description;
        $this->durationMinutes = $session->duration_minutes;

        // Ambos vienen casteados a Carbon por el modelo.
        $this->startDatetime = $session->date->format('Y-m-d')
            . 'T' . $session->start_datetime->format('H:i');

        $this->showCreateModal = true;
    }

    public function updateSession(ReservationService $reservations)
    {
        $this->validate();

        $session = Session::find($this->selectedSessionId);

        if (!$session) {
            session()->flash('error', 'Esa clase ya no existe.');
            $this->closeCreateModal();
            return;
        }

        $room = Room::findOrFail($this->roomId);
        $taken = $reservations->occupiedSpots($session);

        if ($room->capacity < $taken) {
            $this->addError('roomId', "La sala {$room->name} tiene aforo para {$room->capacity} y la clase ya tiene {$taken} reservas.");
            return;
        }

        $dateTime = \Carbon\Carbon::createFromFormat('Y-m-d\TH:i', $this->startDatetime);

        $session->fill([
            'trainer_id' => $this->trainerId,
            'room_id' => $room->id,
            'class_type_id' => $this->classTypeId,
            'name' => $this->sessionName,
            'description' => $this->sessionDescription,
            'capacity' => $room->capacity,
            'date' => $dateTime->format('Y-m-d'),
            'start_datetime' => $dateTime->format('H:i'),
            'duration_minutes' => $this->durationMinutes,
        ]);

        // Cambiar la fecha puede mover el estado, pero una clase cancelada sigue cancelada.
        $session->status = $session->resolveStatus();
        $session->save();

        session()->flash('message', "Clase «{$session->name}» actualizada.");
        $this->closeCreateModal();
    }

    public function confirmDelete($sessionId)
    {
        $session = Session::find($sessionId);

        if (!$session) {
            session()->flash('error', 'Esa clase ya no existe.');
            return;
        }

        $this->selectedSessionId = $session->id;
        $this->reservationsAtRisk = ClientSession::forSession($session->id)->active()->count();
        $this->confirmingDelete = true;
    }

    public function deleteSession()
    {
        $session = Session::find($this->selectedSessionId);

        if (!$session) {
            $this->cancelDelete();
            return;
        }

        // Borrar arrastra en cascada las reservas: si las hay, se cancela la clase.
        if (ClientSession::forSession($session->id)->active()->exists()) {
            session()->flash('error', 'Esta clase tiene reservas activas. Cancélala en lugar de eliminarla.');
            $this->cancelDelete();
            return;
        }

        $name = $session->name;
        $session->delete();

        session()->flash('message', "Clase «{$name}» eliminada.");
        $this->cancelDelete();
    }

    public function cancelDelete()
    {
        $this->confirmingDelete = false;
        $this->selectedSessionId = null;
        $this->reservationsAtRisk = 0;
    }

    public function cancelClass($sessionId, ReservationService $reservations)
    {
        $session = Session::find($sessionId);

        if (!$session) {
            session()->flash('error', 'Esa clase ya no existe.');
            return;
        }

        $cancelled = $reservations->cancelSession($session);

        session()->flash('message', $cancelled > 0
            ? "Clase «{$session->name}» cancelada junto con {$cancelled} reserva(s)."
            : "Clase «{$session->name}» cancelada.");

        $this->cancelDelete();
    }

    public function reactivateClass($sessionId, ReservationService $reservations)
    {
        $session = Session::find($sessionId);

        if (!$session) {
            session()->flash('error', 'Esa clase ya no existe.');
            return;
        }

        $reservations->reactivateSession($session);

        session()->flash('message', "Clase «{$session->name}» reactivada. Los clientes deben volver a reservar.");
    }

    public function render()
    {
        return view('livewire.admin.class-management', [
            'sessions' => $this->getSessions(),
            'trainers' => $this->getTrainers(),
            'rooms' => $this->getRooms(),
            'classTypes' => $this->getClassTypes(),
            'statusLabels' => Session::STATUS_LABELS,
        ]);
    }
}
