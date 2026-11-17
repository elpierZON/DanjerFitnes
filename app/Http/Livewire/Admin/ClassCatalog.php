<?php

namespace App\Http\Livewire\Admin;

use App\Models\ClassType;
use App\Models\Room;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Catálogo de clases del administrador: los tipos de clase y las salas
 * (el "lugar") que luego se eligen al crear una clase en Gestión de Clases.
 *
 * Las clases apuntan a su tipo y a su sala con ON DELETE CASCADE, así que
 * borrar uno que ya se usa arrastraría clases y reservas. Por eso solo se
 * borra lo que no tiene clases; un tipo en uso se desactiva.
 */
class ClassCatalog extends Component
{
    /** Pestaña visible: "types" o "rooms". */
    public $tab = 'types';

    public $searchTerm = '';

    /** Gestión de Clases puede abrir este módulo directamente en una pestaña. */
    protected $listeners = ['showTab' => 'setTab'];

    // Formulario de tipo de clase
    public $showTypeModal = false;
    public $typeId = null;
    public $typeName = '';
    public $typeDescription = '';
    public $typeActive = true;

    // Formulario de sala
    public $showRoomModal = false;
    public $roomId = null;
    public $roomName = '';
    public $roomCapacity = '';
    public $roomDescription = '';

    // Confirmación de borrado
    public $confirmingDelete = false;
    public $deleteKind = null;
    public $deleteId = null;
    public $deleteName = '';
    public $deleteSessions = 0;

    protected function typeRules() : array
    {
        return [
            'typeName' => ['required', 'string', 'max:100', Rule::unique('class_types', 'name')->ignore($this->typeId)],
            'typeDescription' => 'required|string|max:255',
            'typeActive' => 'boolean',
        ];
    }

    protected function roomRules() : array
    {
        return [
            'roomName' => ['required', 'string', 'max:100', Rule::unique('rooms', 'name')->ignore($this->roomId)],
            'roomCapacity' => 'required|integer|min:1|max:500',
            'roomDescription' => 'required|string|max:255',
        ];
    }

    protected function validationAttributes() : array
    {
        return [
            'typeName' => 'nombre',
            'typeDescription' => 'descripción',
            'roomName' => 'nombre',
            'roomCapacity' => 'aforo',
            'roomDescription' => 'descripción',
        ];
    }

    public function setTab($tab)
    {
        $this->tab = $tab === 'rooms' ? 'rooms' : 'types';
        $this->searchTerm = '';
    }

    // ---------- Tipos de clase ----------

    public function openTypeModal($typeId = null)
    {
        $this->resetTypeForm();

        if ($typeId) {
            $type = ClassType::find($typeId);

            if (!$type) {
                session()->flash('error', 'Ese tipo de clase ya no existe.');
                return;
            }

            $this->typeId = $type->id;
            $this->typeName = $type->name;
            $this->typeDescription = $type->description;
            $this->typeActive = (bool) $type->status;
        }

        $this->showTypeModal = true;
    }

    public function closeTypeModal()
    {
        $this->resetTypeForm();
        $this->showTypeModal = false;
    }

    private function resetTypeForm()
    {
        $this->typeId = null;
        $this->typeName = '';
        $this->typeDescription = '';
        $this->typeActive = true;
        $this->resetErrorBag();
    }

    public function saveType()
    {
        $this->typeName = trim($this->typeName);
        $this->validate($this->typeRules());

        $data = [
            'name' => $this->typeName,
            'description' => trim($this->typeDescription),
            'status' => (bool) $this->typeActive,
        ];

        if ($this->typeId) {
            $type = ClassType::find($this->typeId);

            if (!$type) {
                session()->flash('error', 'Ese tipo de clase ya no existe.');
                $this->closeTypeModal();
                return;
            }

            $type->update($data);
            session()->flash('message', "Tipo de clase «{$type->name}» actualizado.");
        } else {
            $type = ClassType::create($data);
            session()->flash('message', "Tipo de clase «{$type->name}» creado. Ya puedes usarlo al crear una clase.");
        }

        $this->closeTypeModal();
    }

    /** Un tipo inactivo deja de ofrecerse al crear clases; las existentes no cambian. */
    public function toggleType($typeId)
    {
        $type = ClassType::find($typeId);

        if (!$type) {
            session()->flash('error', 'Ese tipo de clase ya no existe.');
            return;
        }

        $type->update(['status' => !$type->status]);

        session()->flash('message', $type->status
            ? "Tipo «{$type->name}» activado."
            : "Tipo «{$type->name}» desactivado: ya no aparecerá al crear clases.");
    }

    // ---------- Salas ----------

    public function openRoomModal($roomId = null)
    {
        $this->resetRoomForm();

        if ($roomId) {
            $room = Room::find($roomId);

            if (!$room) {
                session()->flash('error', 'Esa sala ya no existe.');
                return;
            }

            $this->roomId = $room->id;
            $this->roomName = $room->name;
            $this->roomCapacity = $room->capacity;
            $this->roomDescription = $room->description;
        }

        $this->showRoomModal = true;
    }

    public function closeRoomModal()
    {
        $this->resetRoomForm();
        $this->showRoomModal = false;
    }

    private function resetRoomForm()
    {
        $this->roomId = null;
        $this->roomName = '';
        $this->roomCapacity = '';
        $this->roomDescription = '';
        $this->resetErrorBag();
    }

    public function saveRoom()
    {
        $this->roomName = trim($this->roomName);
        $this->validate($this->roomRules());

        $data = [
            'name' => $this->roomName,
            'capacity' => (int) $this->roomCapacity,
            'description' => trim($this->roomDescription),
        ];

        if ($this->roomId) {
            $room = Room::find($this->roomId);

            if (!$room) {
                session()->flash('error', 'Esa sala ya no existe.');
                $this->closeRoomModal();
                return;
            }

            $room->update($data);
            session()->flash('message', "Sala «{$room->name}» actualizada. Las clases ya creadas conservan su aforo.");
        } else {
            $room = Room::create($data);
            session()->flash('message', "Sala «{$room->name}» creada. Ya puedes usarla al crear una clase.");
        }

        $this->closeRoomModal();
    }

    // ---------- Borrado (de ambos) ----------

    public function confirmDelete($kind, $id)
    {
        $model = $kind === 'room' ? Room::find($id) : ClassType::find($id);

        if (!$model) {
            session()->flash('error', 'Ese registro ya no existe.');
            return;
        }

        $this->deleteKind = $kind === 'room' ? 'room' : 'type';
        $this->deleteId = $model->id;
        $this->deleteName = $model->name;
        $this->deleteSessions = $model->sessions()->count();
        $this->confirmingDelete = true;
    }

    public function delete()
    {
        $model = $this->deleteKind === 'room' ? Room::find($this->deleteId) : ClassType::find($this->deleteId);

        if (!$model) {
            $this->cancelDelete();
            return;
        }

        // Se vuelve a contar: alguien pudo crear una clase mientras el modal estaba abierto.
        if ($model->sessions()->exists()) {
            session()->flash('error', "«{$model->name}» tiene clases asociadas y no se puede eliminar.");
            $this->cancelDelete();
            return;
        }

        $name = $model->name;
        $model->delete();

        session()->flash('message', $this->deleteKind === 'room'
            ? "Sala «{$name}» eliminada."
            : "Tipo de clase «{$name}» eliminado.");
        $this->cancelDelete();
    }

    /** Alternativa que ofrece el modal cuando el tipo ya tiene clases. */
    public function deactivateInsteadOfDelete()
    {
        $type = $this->deleteKind === 'type' ? ClassType::find($this->deleteId) : null;

        if ($type) {
            $type->update(['status' => false]);
            session()->flash('message', "Tipo «{$type->name}» desactivado: ya no aparecerá al crear clases.");
        }

        $this->cancelDelete();
    }

    public function cancelDelete()
    {
        $this->confirmingDelete = false;
        $this->deleteKind = null;
        $this->deleteId = null;
        $this->deleteName = '';
        $this->deleteSessions = 0;
    }

    public function render()
    {
        $term = trim($this->searchTerm);
        $search = fn($query) => $query->when($term !== '', function ($q) use ($term) {
            $q->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        });

        return view('livewire.admin.class-catalog', [
            'classTypes' => $this->tab === 'types'
                ? $search(ClassType::query())->withCount('sessions')->orderBy('name')->get()
                : collect(),
            'rooms' => $this->tab === 'rooms'
                ? $search(Room::query())->withCount('sessions')->orderBy('name')->get()
                : collect(),
            'typesTotal' => ClassType::count(),
            'roomsTotal' => Room::count(),
        ]);
    }
}
