<?php

namespace App\Http\Livewire\Dashboard;

use App\Models\ClientSession;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Stats extends Component
{
    public $reservedClasses = 0;
    public $completedClasses = 0;
    public $monthlyClasses = 0;
    public $currentPlan = 'Sin plan';

    protected $listeners = ['reservationUpdated' => 'loadStats'];

    public function mount()
    {
        $this->loadStats();
    }

    public function loadStats()
    {
        $user = Auth::user();
        $client = $user->client;

        if (!$client) {
            return;
        }

        // Clases reservadas (estados: reserved o confirmed)
        $this->reservedClasses = $client->activeSessions()->count();

        // Clases completadas (estado: completed y asistencia verdadera)
        $this->completedClasses = $client->sessions()
            ->wherePivot('status', ClientSession::STATUS_COMPLETED)
            ->wherePivot('attendance', true)
            ->count();

        // Clases de este mes (reservadas, confirmadas o completadas)
        $this->monthlyClasses = $client->sessions()
            ->whereMonth('sessions.date', now()->month)
            ->whereYear('sessions.date', now()->year)
            ->wherePivotIn('status', [
                ClientSession::STATUS_RESERVED,
                ClientSession::STATUS_CONFIRMED,
                ClientSession::STATUS_COMPLETED,
            ])
            ->count();

        // Plan actual (membresía activa más reciente; status es booleano)
        $activeMembership = $client->memberships()
            ->where('status', 1)
            ->with('plan')
            ->latest()
            ->first();

        if ($activeMembership && $activeMembership->plan) {
            $this->currentPlan = $activeMembership->plan->type;
        }
    }

    public function render()
    {
        return view('livewire.dashboard.stats');
    }
}
