<div>
    <div class="class-management-container">
        <!-- Panel Principal -->
        <div class="class-management-main">
            <div class="class-management-header">
                <h2>Gestión de Clases</h2>
            </div>

            @if (session()->has('message'))
                <div class="alert alert-success">{{ session('message') }}</div>
            @endif

            @if (session()->has('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif

            <div class="class-management-controls">
                <div class="search-box" style="max-width: 400px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <input type="text"
                           placeholder="Buscar clases..."
                           wire:model.debounce.300ms="searchTerm">
                </div>

                <button class="btn btn-primary" wire:click="openCreateModal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Nueva Clase
                </button>
            </div>

            <div wire:loading class="loading-spinner">
                Cargando clases...
            </div>

            @if($sessions->isEmpty())
                <div class="empty-state">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <p>No se encontraron clases</p>
                </div>
            @else
                <div class="sessions-table-container">
                    <table class="sessions-table">
                        <thead>
                            <tr>
                                <th>Clase</th>
                                <th>Entrenador</th>
                                <th>Lugar</th>
                                <th>Ocupación</th>
                                <th>Fecha y Hora</th>
                                <th>Duración</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sessions as $session)
                                <tr wire:key="session-{{ $session->id }}">
                                    <td>
                                        <div class="session-info">
                                            <strong>{{ $session->name }}</strong>
                                            <p class="session-type">{{ $session->classType->name ?? 'N/A' }}</p>
                                        </div>
                                    </td>
                                    <td>
                                        @if($session->trainer?->user)
                                            <div class="trainer-info">
                                                <span class="trainer-avatar">{{ substr($session->trainer->user->name, 0, 1) }}{{ substr($session->trainer->user->lastname, 0, 1) }}</span>
                                                <div>
                                                    <strong>{{ $session->trainer->user->name }} {{ $session->trainer->user->lastname }}</strong>
                                                    <p class="trainer-specialty">{{ $session->trainer->speciality ?: 'Sin especialidad' }}</p>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted">Sin entrenador</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="room-info">
                                            <strong>{{ $session->room->name ?? 'Sin sala' }}</strong>
                                            <p class="room-capacity">Aforo: {{ $session->room->capacity ?? $session->capacity }} personas</p>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="capacity-badge">{{ $session->taken_spots }}/{{ $session->capacity }}</span>
                                    </td>
                                    <td>
                                        <div class="datetime-info">
                                            <span class="date">{{ $session->date->format('d/m/Y') }}</span>
                                            <span class="time">{{ $session->start_datetime->format('H:i') }} - {{ $session->end_time }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="duration-badge">{{ $session->duration_minutes }}min</span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-{{ $session->status }}">
                                            {{ $statusLabels[$session->status] ?? $session->status }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-primary"
                                                    wire:click="editSession({{ $session->id }})">
                                                Editar
                                            </button>

                                            @if($session->status === \App\Models\Session::STATUS_CANCELLED)
                                                <button class="btn btn-sm btn-secondary"
                                                        wire:click="reactivateClass({{ $session->id }})">
                                                    Reactivar
                                                </button>
                                            @else
                                                <button class="btn btn-sm btn-warning"
                                                        wire:click="cancelClass({{ $session->id }})">
                                                    Cancelar
                                                </button>
                                            @endif

                                            <button class="btn btn-sm btn-danger"
                                                    wire:click="confirmDelete({{ $session->id }})">
                                                Eliminar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="pagination-wrapper">
                    {{ $sessions->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Modal de Crear/Editar Clase -->
    @if($showCreateModal)
        <div class="modal-overlay" wire:click.self="closeCreateModal">
            <div class="modal-content modal-lg">
                <div class="modal-header">
                    <h3>{{ $selectedSessionId ? 'Editar Clase' : 'Crear Nueva Clase' }}</h3>
                    <button class="modal-close" wire:click="closeCreateModal">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="sessionName">Nombre de la Clase</label>
                            <input type="text"
                                   id="sessionName"
                                   wire:model.lazy="sessionName"
                                   maxlength="100"
                                   class="form-input"
                                   placeholder="Ej: Yoga Mañana">
                            @error('sessionName') <span class="error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label for="classTypeId">Tipo de Clase</label>
                            <select id="classTypeId"
                                    wire:model.lazy="classTypeId"
                                    class="form-input">
                                <option value="">-- Selecciona un tipo --</option>
                                @foreach($classTypes as $type)
                                    <option value="{{ $type->id }}" wire:key="type-{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                            @error('classTypeId') <span class="error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label for="trainerId">Entrenador</label>
                            <select id="trainerId"
                                    wire:model.lazy="trainerId"
                                    class="form-input">
                                <option value="">-- Selecciona un entrenador --</option>
                                @foreach($trainers as $trainer)
                                    <option value="{{ $trainer->id }}" wire:key="trainer-{{ $trainer->id }}">{{ $trainer->user->name }} {{ $trainer->user->lastname }}</option>
                                @endforeach
                            </select>
                            @error('trainerId') <span class="error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label for="roomId">Lugar</label>
                            <select id="roomId"
                                    wire:model.lazy="roomId"
                                    class="form-input">
                                <option value="">-- Selecciona una sala --</option>
                                @foreach($rooms as $room)
                                    <option value="{{ $room->id }}" wire:key="room-{{ $room->id }}">{{ $room->name }} (Aforo: {{ $room->capacity }})</option>
                                @endforeach
                            </select>
                            @error('roomId') <span class="error">{{ $message }}</span> @enderror
                            <small class="form-hint">La capacidad de la clase es la de la sala elegida.</small>
                        </div>

                        <div class="form-group">
                            <label for="startDatetime">Fecha y Hora</label>
                            <input type="datetime-local"
                                   id="startDatetime"
                                   wire:model.lazy="startDatetime"
                                   class="form-input">
                            @error('startDatetime') <span class="error">{{ $message }}</span> @enderror
                            <small class="form-hint">El estado (programada, en progreso o completada) se calcula con esta fecha.</small>
                        </div>

                        <div class="form-group">
                            <label for="durationMinutes">Duración (minutos)</label>
                            <input type="number"
                                   id="durationMinutes"
                                   wire:model.lazy="durationMinutes"
                                   min="1"
                                   max="480"
                                   class="form-input"
                                   placeholder="Ej: 60">
                            @error('durationMinutes') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="sessionDescription">Descripción</label>
                        <textarea id="sessionDescription"
                                  wire:model.lazy="sessionDescription"
                                  class="form-input"
                                  rows="3"
                                  maxlength="300"
                                  placeholder="Descripción de la clase..."></textarea>
                        @error('sessionDescription') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" wire:click="closeCreateModal">
                        Cancelar
                    </button>
                    <button class="btn btn-primary"
                            wire:click="{{ $selectedSessionId ? 'updateSession' : 'createSession' }}"
                            wire:loading.attr="disabled">
                        <span wire:loading.remove>{{ $selectedSessionId ? 'Actualizar' : 'Crear' }} Clase</span>
                        <span wire:loading>Procesando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal de Confirmar Eliminación -->
    @if($confirmingDelete)
        <div class="modal-overlay" wire:click.self="cancelDelete">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>{{ $reservationsAtRisk > 0 ? 'No se puede eliminar' : 'Confirmar Eliminación' }}</h3>
                    <button class="modal-close" wire:click="cancelDelete">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>

                <div class="modal-body">
                    @if($reservationsAtRisk > 0)
                        <p>
                            Esta clase tiene <strong>{{ $reservationsAtRisk }}</strong> reserva(s) activa(s).
                            Eliminarla borraría también el historial de esos clientes.
                        </p>
                        <p>Cancélala en su lugar: la clase queda marcada como cancelada y sus reservas también, sin perder nada.</p>
                    @else
                        <p>¿Seguro que quieres eliminar esta clase? Esta acción no se puede deshacer.</p>
                    @endif
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" wire:click="cancelDelete">
                        Cerrar
                    </button>

                    @if($reservationsAtRisk > 0)
                        <button class="btn btn-warning"
                                wire:click="cancelClass({{ $selectedSessionId }})"
                                wire:loading.attr="disabled">
                            <span wire:loading.remove>Cancelar la clase</span>
                            <span wire:loading>Procesando...</span>
                        </button>
                    @else
                        <button class="btn btn-danger" wire:click="deleteSession" wire:loading.attr="disabled">
                            <span wire:loading.remove>Eliminar</span>
                            <span wire:loading>Eliminando...</span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
