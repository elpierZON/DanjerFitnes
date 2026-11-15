<?php

namespace App\Http\Livewire\Trainer;

use App\Exceptions\ReservationException;
use App\Models\Session;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MyClasses extends Component
{
    public $filter = 'upcoming'; // upcoming, today, past, all
    public $classes = [];
    public $selectedClass = null;
    public $showStudentsModal = false;

    public function mount()
    {
        $this->loadClasses();
    }

    public function loadClasses()
    {
        $trainer = Auth::user()->trainer;

        if (!$trainer) {
            $this->classes = [];
            return;
        }

        $query = $trainer->sessions()
            ->with(['clients.user', 'room', 'classType'])
            ->orderBy('date')
            ->orderBy('start_datetime');

        switch ($this->filter) {
            case 'upcoming':
                $query->where('date', '>', Carbon::today())
                      ->where('status', 'scheduled');
                break;
            case 'today':
                $query->whereDate('date', Carbon::today());
                break;
            case 'past':
                $query->where('date', '<', Carbon::today());
                break;
            case 'all':
                // No filter
                break;
        }

        $this->classes = $query->get();
    }

    public function setFilter($filter)
    {
        $this->filter = $filter;
        $this->loadClasses();
    }

    public function viewStudents($sessionId)
    {
        $this->selectedClass = Session::with(['clients.user', 'classType', 'room'])
            ->findOrFail($sessionId);
        $this->showStudentsModal = true;
    }

    public function closeModal()
    {
        $this->showStudentsModal = false;
        $this->selectedClass = null;
    }

    /**
     * Pasar lista: marcar asistencia completa la reserva en la base de datos,
     * desmarcarla la devuelve a confirmada.
     */
    public function toggleAttendance($sessionId, $clientId, ReservationService $reservations)
    {
        $session = Session::findOrFail($sessionId);
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

        $this->loadClasses();

        if ($this->selectedClass && $this->selectedClass->id == $sessionId) {
            $this->selectedClass = Session::with(['clients.user', 'classType', 'room'])
                ->findOrFail($sessionId);
        }

        $this->emit('classUpdated');
    }

    public function render()
    {
        return view('livewire.trainer.my-classes');
    }
}
