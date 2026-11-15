<?php

namespace App\Http\Livewire\Admin;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\User;
use App\Models\Administrator;
use App\Models\Trainer;
use App\Models\Client;
use App\Models\ClassType;
use Illuminate\Support\Facades\DB;

class UserRoles extends Component
{
    public $searchTerm = '';
    public $confirmingRoleChange = false;
    public $selectedUserId = null;
    public $newRole = null;
    public $selectedSpeciality = null;
    public $selectedYearsExperience = 0;
    public $showSpecialityModal = false;

    public function getUsers()
    {
        return User::where('name', 'like', '%' . $this->searchTerm . '%')
            ->orWhere('lastname', 'like', '%' . $this->searchTerm . '%')
            ->orWhere('dni', 'like', '%' . $this->searchTerm . '%')
            ->orWhere('email', 'like', '%' . $this->searchTerm . '%')
            ->get();
    }

    public function getSpecialities()
    {
        return ClassType::all();
    }

    public function getUserRole($userId)
    {
        if (Administrator::where('user_id', $userId)->exists()) {
            return 'administrator';
        }
        if (Trainer::where('user_id', $userId)->exists()) {
            return 'trainer';
        }
        if (Client::where('user_id', $userId)->exists()) {
            return 'client';
        }
        return 'sin_rol';
    }

    public function confirmRoleChange($userId, $newRole)
    {
        $this->selectedUserId = $userId;
        $this->newRole = $newRole;
        
        // Si es entrenador, mostrar modal de especialidad
        if ($newRole === 'trainer') {
            $this->showSpecialityModal = true;
        } else {
            $this->confirmingRoleChange = true;
        }
    }

    public function selectSpeciality($specialityId)
    {
        $this->selectedSpeciality = $specialityId;
        $this->showSpecialityModal = false;
        $this->confirmingRoleChange = true;
    }

    public function confirmTrainerDetails()
    {
        if (!$this->selectedSpeciality) {
            return;
        }
        
        $this->showSpecialityModal = false;
        $this->confirmingRoleChange = true;
    }

    public function cancelRoleChange()
    {
        $this->confirmingRoleChange = false;
        $this->showSpecialityModal = false;
        $this->selectedUserId = null;
        $this->newRole = null;
        $this->selectedSpeciality = null;
        $this->selectedYearsExperience = 0;
    }

    public function changeUserRole()
    {
        if (!$this->selectedUserId || !$this->newRole) {
            return;
        }

        $user = User::find($this->selectedUserId);
        if (!$user) {
            $this->cancelRoleChange();
            return;
        }

        DB::transaction(function () {
            // Desactivar los otros roles. Es borrado lógico: la fila se conserva,
            // así que el cliente no pierde sus reservas ni el entrenador sus clases.
            foreach ($this->roleModels() as $role => $model) {
                if ($role !== $this->newRole) {
                    $model::where('user_id', $this->selectedUserId)->delete();
                }
            }

            // Reactivar el rol destino si ya existió alguna vez, o crearlo.
            $model = $this->roleModels()[$this->newRole] ?? null;

            if (!$model) {
                return;
            }

            $existing = $model::withTrashed()
                ->where('user_id', $this->selectedUserId)
                ->first();

            $attributes = $this->newRole === 'trainer'
                ? [
                    'speciality' => ClassType::find($this->selectedSpeciality)?->name ?? 'General',
                    'years_of_experience' => $this->selectedYearsExperience ?? 0,
                ]
                : [];

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                // Solo el entrenador tiene datos que el admin vuelve a capturar.
                if ($attributes) {
                    $existing->update($attributes);
                }
            } else {
                $model::create($attributes + ['user_id' => $this->selectedUserId]);
            }
        });

        session()->flash('message', "Rol actualizado a {$this->roleLabel($this->newRole)}. No se borró ningún dato del usuario.");

        $this->cancelRoleChange();
    }

    /** @return array<string, class-string<\Illuminate\Database\Eloquent\Model>> */
    private function roleModels() : array
    {
        return [
            'administrator' => Administrator::class,
            'trainer'       => Trainer::class,
            'client'        => Client::class,
        ];
    }

    private function roleLabel(string $role) : string
    {
        return [
            'administrator' => 'administrador',
            'trainer'       => 'entrenador',
            'client'        => 'cliente',
        ][$role] ?? $role;
    }

    public function render()
    {
        return view('livewire.admin.user-roles', [
            'users' => $this->getUsers(),
            'specialities' => $this->getSpecialities(),
        ]);
    }
}
