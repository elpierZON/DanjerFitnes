<div>
    @if (session()->has('message'))
        <div class="alert alert-success">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-error">
            {{ session('error') }}
        </div>
    @endif

    <div class="section-header">
        <h2>Mis Reservas</h2>
        <p>Historial y estado de todas tus clases reservadas</p>
    </div>

    <div class="reservation-filters">
        <button class="filter-btn {{ $filter === 'all' ? 'active' : '' }}" wire:click="setFilter('all')">
            Todas <span class="filter-count">{{ $counts['all'] ?? 0 }}</span>
        </button>
        @foreach($statusLabels as $status => $label)
            <button class="filter-btn {{ $filter === $status ? 'active' : '' }}" wire:click="setFilter('{{ $status }}')">
                {{ $label }}s <span class="filter-count">{{ $counts[$status] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    <div class="reservations-list">
        @forelse($reservations as $session)
            @php
                $reservation = $session->pivot;
                $isUpcoming = $session->date->endOfDay()->greaterThanOrEqualTo($today);
            @endphp

            <div class="reservation-card status-{{ $reservation->status }}" wire:key="reservation-{{ $reservation->id }}">
                <div class="reservation-main">
                    <div class="reservation-heading">
                        <h3>{{ $session->name }}</h3>
                        <span class="reservation-status badge-{{ $reservation->status }}">
                            {{ $statusLabels[$reservation->status] ?? $reservation->status }}
                        </span>
                    </div>

                    <p class="reservation-type">{{ $session->classType->name ?? 'Clase' }}</p>

                    <div class="reservation-meta">
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                            {{ ucfirst($session->day_name) }} {{ $session->date->format('d/m/Y') }}
                        </span>
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            {{ $session->start_datetime->format('H:i') }} - {{ $session->end_time }}
                        </span>
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            {{ $session->trainer->user->name ?? 'N/A' }} {{ $session->trainer->user->lastname ?? '' }}
                        </span>
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            {{ $session->room->name ?? 'N/A' }}
                        </span>
                    </div>

                    <div class="reservation-trace">
                        @if($reservation->reserved_at)
                            <span>Reservada el {{ $reservation->reserved_at->format('d/m/Y H:i') }}</span>
                        @endif
                        @if($reservation->cancelled_at)
                            <span>Cancelada el {{ $reservation->cancelled_at->format('d/m/Y H:i') }}</span>
                        @endif
                        @if($reservation->status === 'completed')
                            <span>{{ $reservation->attendance ? 'Asistencia registrada ✓' : 'Sin asistencia' }}</span>
                        @endif
                    </div>
                </div>

                <div class="reservation-actions">
                    @if($reservation->status === 'reserved' && $isUpcoming)
                        <button class="btn-confirm" wire:click="confirmReservation({{ $session->id }})"
                            wire:loading.attr="disabled" wire:target="confirmReservation({{ $session->id }})">
                            Confirmar asistencia
                        </button>
                    @endif

                    @if(in_array($reservation->status, ['reserved', 'confirmed']) && $isUpcoming)
                        <button class="btn-cancel-reservation" wire:click="cancelReservation({{ $session->id }})"
                            wire:loading.attr="disabled" wire:target="cancelReservation({{ $session->id }})">
                            Cancelar
                        </button>
                    @endif

                    @if($reservation->status === 'cancelled' && $isUpcoming)
                        <button class="btn-reserve" wire:click="reactivateReservation({{ $session->id }})"
                            wire:loading.attr="disabled" wire:target="reactivateReservation({{ $session->id }})">
                            Volver a reservar
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="empty-state">
                <p>No tienes reservas {{ $filter === 'all' ? '' : ($statusLabels[$filter] ?? '') . 's' }} por el momento.</p>
                <button class="btn-select-type" style="max-width: 260px; margin: 1rem auto 0;"
                    wire:click="$emit('navigateTo', 'clases')">
                    Explorar clases
                </button>
            </div>
        @endforelse
    </div>

    @if($hasMore)
        <div style="text-align: center; margin-top: 1.5rem;">
            <button class="btn-load-more" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore">
                <span wire:loading.remove wire:target="loadMore">Cargar más reservas</span>
                <span wire:loading wire:target="loadMore">Cargando...</span>
            </button>
        </div>
    @endif
</div>
