<div>
    <div class="class-management-container">
        <div class="class-management-main catalog-main">
            <div class="class-management-header">
                <h2>Tipos de Clase y Salas</h2>
                <p class="catalog-subtitle">Lo que registres aquí aparece en el formulario de «Nueva Clase».</p>
            </div>

            @if (session()->has('message'))
                <div class="alert alert-success">{{ session('message') }}</div>
            @endif

            @if (session()->has('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif

            <div class="catalog-tabs" role="tablist">
                <button type="button"
                        role="tab"
                        class="catalog-tab {{ $tab === 'types' ? 'active' : '' }}"
                        aria-selected="{{ $tab === 'types' ? 'true' : 'false' }}"
                        wire:click="setTab('types')">
                    Tipos de clase <span class="catalog-count">{{ $typesTotal }}</span>
                </button>
                <button type="button"
                        role="tab"
                        class="catalog-tab {{ $tab === 'rooms' ? 'active' : '' }}"
                        aria-selected="{{ $tab === 'rooms' ? 'true' : 'false' }}"
                        wire:click="setTab('rooms')">
                    Salas <span class="catalog-count">{{ $roomsTotal }}</span>
                </button>
            </div>

            <div class="class-management-controls">
                <div class="search-box" style="max-width: 400px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <input type="text"
                           placeholder="{{ $tab === 'types' ? 'Buscar tipos de clase...' : 'Buscar salas...' }}"
                           wire:model.debounce.300ms="searchTerm">
                </div>

                <button class="btn btn-primary" wire:click="{{ $tab === 'types' ? 'openTypeModal' : 'openRoomModal' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    {{ $tab === 'types' ? 'Nuevo Tipo' : 'Nueva Sala' }}
                </button>
            </div>

            @if($tab === 'types')
                @if($classTypes->isEmpty())
                    <div class="empty-state">
                        <p>{{ $searchTerm !== '' ? 'Ningún tipo coincide con la búsqueda' : 'Aún no hay tipos de clase. Crea el primero.' }}</p>
                    </div>
                @else
                    <div class="sessions-table-container">
                        <table class="sessions-table">
                            <thead>
                                <tr>
                                    <th>Tipo de clase</th>
                                    <th>Clases</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($classTypes as $type)
                                    <tr wire:key="type-row-{{ $type->id }}">
                                        <td>
                                            <div class="session-info">
                                                <strong>{{ $type->name }}</strong>
                                                <p class="session-type">{{ $type->description }}</p>
                                            </div>
                                        </td>
                                        <td><span class="capacity-badge">{{ $type->sessions_count }}</span></td>
                                        <td>
                                            <span class="status-badge {{ $type->status ? 'status-active' : 'status-inactive' }}">
                                                {{ $type->status ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-primary" wire:click="openTypeModal({{ $type->id }})">Editar</button>
                                                <button class="btn btn-sm {{ $type->status ? 'btn-warning' : 'btn-secondary' }}"
                                                        wire:click="toggleType({{ $type->id }})">
                                                    {{ $type->status ? 'Desactivar' : 'Activar' }}
                                                </button>
                                                <button class="btn btn-sm btn-danger" wire:click="confirmDelete('type', {{ $type->id }})">Eliminar</button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @else
                @if($rooms->isEmpty())
                    <div class="empty-state">
                        <p>{{ $searchTerm !== '' ? 'Ninguna sala coincide con la búsqueda' : 'Aún no hay salas. Crea la primera.' }}</p>
                    </div>
                @else
                    <div class="sessions-table-container">
                        <table class="sessions-table">
                            <thead>
                                <tr>
                                    <th>Sala</th>
                                    <th>Aforo</th>
                                    <th>Clases</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rooms as $room)
                                    <tr wire:key="room-row-{{ $room->id }}">
                                        <td>
                                            <div class="room-info">
                                                <strong>{{ $room->name }}</strong>
                                                <p class="room-capacity">{{ $room->description }}</p>
                                            </div>
                                        </td>
                                        <td><span class="duration-badge">{{ $room->capacity }} personas</span></td>
                                        <td><span class="capacity-badge">{{ $room->sessions_count }}</span></td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-sm btn-primary" wire:click="openRoomModal({{ $room->id }})">Editar</button>
                                                <button class="btn btn-sm btn-danger" wire:click="confirmDelete('room', {{ $room->id }})">Eliminar</button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
    </div>

    <!-- Modal de Tipo de Clase -->
    @if($showTypeModal)
        <div class="modal-overlay" wire:click.self="closeTypeModal">
            <div class="modal-content modal-lg">
                <div class="modal-header">
                    <h3>{{ $typeId ? 'Editar Tipo de Clase' : 'Nuevo Tipo de Clase' }}</h3>
                    <button class="modal-close" wire:click="closeTypeModal" aria-label="Cerrar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="form-group">
                        <label for="typeName">Nombre</label>
                        <input type="text" id="typeName" wire:model.defer="typeName" maxlength="100"
                               class="form-input" placeholder="Ej: Yoga">
                        @error('typeName') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group">
                        <label for="typeDescription">Descripción</label>
                        <textarea id="typeDescription" wire:model.defer="typeDescription" maxlength="255" rows="3"
                                  class="form-input" placeholder="De qué trata este tipo de clase..."></textarea>
                        @error('typeDescription') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group">
                        <label class="catalog-check">
                            <input type="checkbox" wire:model.defer="typeActive">
                            Activo (se ofrece al crear clases)
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" wire:click="closeTypeModal">Cancelar</button>
                    <button class="btn btn-primary" wire:click="saveType" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveType">{{ $typeId ? 'Guardar cambios' : 'Crear tipo' }}</span>
                        <span wire:loading wire:target="saveType">Guardando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal de Sala -->
    @if($showRoomModal)
        <div class="modal-overlay" wire:click.self="closeRoomModal">
            <div class="modal-content modal-lg">
                <div class="modal-header">
                    <h3>{{ $roomId ? 'Editar Sala' : 'Nueva Sala' }}</h3>
                    <button class="modal-close" wire:click="closeRoomModal" aria-label="Cerrar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="roomName">Nombre</label>
                            <input type="text" id="roomName" wire:model.defer="roomName" maxlength="100"
                                   class="form-input" placeholder="Ej: Sala de Yoga">
                            @error('roomName') <span class="error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label for="roomCapacity">Aforo (personas)</label>
                            <input type="number" id="roomCapacity" wire:model.defer="roomCapacity" min="1" max="500"
                                   class="form-input" placeholder="Ej: 20">
                            @error('roomCapacity') <span class="error">{{ $message }}</span> @enderror
                            <small class="form-hint">Las clases nuevas en esta sala toman este aforo.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="roomDescription">Descripción</label>
                        <textarea id="roomDescription" wire:model.defer="roomDescription" maxlength="255" rows="3"
                                  class="form-input" placeholder="Equipamiento, ubicación..."></textarea>
                        @error('roomDescription') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" wire:click="closeRoomModal">Cancelar</button>
                    <button class="btn btn-primary" wire:click="saveRoom" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveRoom">{{ $roomId ? 'Guardar cambios' : 'Crear sala' }}</span>
                        <span wire:loading wire:target="saveRoom">Guardando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal de Eliminar -->
    @if($confirmingDelete)
        <div class="modal-overlay" wire:click.self="cancelDelete">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>{{ $deleteSessions > 0 ? 'No se puede eliminar' : 'Confirmar Eliminación' }}</h3>
                    <button class="modal-close" wire:click="cancelDelete" aria-label="Cerrar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>

                <div class="modal-body">
                    @if($deleteSessions > 0)
                        <p>
                            «{{ $deleteName }}» tiene <strong>{{ $deleteSessions }}</strong> clase(s) asociada(s).
                            Si se elimina, se borrarían también esas clases y sus reservas.
                        </p>
                        @if($deleteKind === 'type')
                            <p>Desactívalo en su lugar: deja de ofrecerse al crear clases y no se pierde nada.</p>
                        @else
                            <p>Cambia primero esas clases a otra sala desde «Gestión de Clases».</p>
                        @endif
                    @else
                        <p>¿Seguro que quieres eliminar «{{ $deleteName }}»? Esta acción no se puede deshacer.</p>
                    @endif
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" wire:click="cancelDelete">Cerrar</button>

                    @if($deleteSessions > 0 && $deleteKind === 'type')
                        <button class="btn btn-warning" wire:click="deactivateInsteadOfDelete">Desactivar</button>
                    @elseif($deleteSessions == 0)
                        <button class="btn btn-danger" wire:click="delete" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="delete">Eliminar</span>
                            <span wire:loading wire:target="delete">Eliminando...</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
