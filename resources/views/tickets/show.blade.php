@extends('layouts.app')

@section('title', $batch->original_filename)

@section('content')
    @php
        $allTickets = $batch->tickets->sortBy('position')->values();
        $revealed = $allTickets->where('revealed', true)->values();
        $startIndex = 0;
        if ($currentTicket) {
            $found = $allTickets->search(fn ($t) => $t->id === $currentTicket->id);
            if ($found !== false) {
                $startIndex = (int) $found;
            }
        } elseif ($revealed->isNotEmpty()) {
            $last = $revealed->last();
            $found = $allTickets->search(fn ($t) => $t->id === $last->id);
            if ($found !== false) {
                $startIndex = (int) $found;
            }
        }
    @endphp

    <section class="panel">
        <h1>{{ $batch->durationLabel() }} · {{ $batch->original_filename }}</h1>
        <p class="lead">
            Lot <strong>{{ $batch->durationLabel() }}</strong> — cliquez sur
            <strong>Nouveau ticket</strong> pour sortir la prochaine image.
        </p>

        <div class="stats">
            <div class="stat">
                <div class="n">{{ $batch->total_tickets }}</div>
                <div class="l">Total</div>
            </div>
            <div class="stat">
                <div class="n">{{ $batch->revealedCount() }}</div>
                <div class="l">Déjà sortis</div>
            </div>
            <div class="stat">
                <div class="n">{{ $batch->remainingCount() }}</div>
                <div class="l">Restants</div>
            </div>
        </div>

        @if ($currentTicket)
            <div class="ticket-card ticket-card--image">
                <div class="label">Dernier sorti · #{{ $currentTicket->ticket_no ?? $currentTicket->position }}</div>
                <x-ticket-image :ticket="$currentTicket" />
            </div>
        @else
            <p class="empty">Aucun ticket affiché pour l’instant. Cliquez sur Nouveau ticket.</p>
        @endif

        <div class="actions">
            <form action="{{ route('tickets.next', $batch) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary btn-lg" @disabled($batch->isExhausted())>
                    {{ $batch->isExhausted() ? 'Plus de tickets' : 'Nouveau ticket' }}
                </button>
            </form>
            <a class="btn btn-ghost" href="{{ route('tickets.index') }}">Retour</a>
            <form action="{{ route('tickets.destroy', $batch) }}" method="POST" onsubmit="return confirm('Supprimer ce lot et tous ses tickets ?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">Supprimer le lot</button>
            </form>
        </div>
    </section>

    <section class="panel">
        <h2>Parcourir les tickets</h2>
        <p class="lead">Utilisez les boutons gauche / droite pour voir le ticket 1, 2, 3… un par un.</p>

        @if ($allTickets->isEmpty())
            <p class="empty">Aucun ticket dans ce lot.</p>
        @else
            <div class="ticket-carousel" id="ticket-carousel">
                <button type="button" class="carousel-btn carousel-btn--prev" id="carousel-prev" aria-label="Précédent">‹</button>

                <div class="carousel-stage">
                    <div class="carousel-meta" id="carousel-meta"></div>
                    <div class="carousel-viewport">
                        @foreach ($allTickets as $i => $ticket)
                            <div
                                class="carousel-slide{{ $i === $startIndex ? ' is-active' : '' }}"
                                data-index="{{ $i }}"
                                data-no="{{ $ticket->ticket_no ?? $ticket->position }}"
                                data-revealed="{{ $ticket->revealed ? '1' : '0' }}"
                                data-date="{{ $ticket->revealed_at?->format('d/m/Y H:i') ?? '' }}"
                            >
                                <x-ticket-image :ticket="$ticket" />
                            </div>
                        @endforeach
                    </div>
                    <div class="carousel-counter">
                        Ticket <span id="carousel-pos">{{ $startIndex + 1 }}</span>
                        / {{ $allTickets->count() }}
                        <span id="carousel-badge" class="badge" style="margin-left:.5rem;"></span>
                    </div>
                </div>

                <button type="button" class="carousel-btn carousel-btn--next" id="carousel-next" aria-label="Suivant">›</button>
            </div>

            <script>
            (function () {
                var root = document.getElementById('ticket-carousel');
                if (!root) return;

                var slides = root.querySelectorAll('.carousel-slide');
                var meta = document.getElementById('carousel-meta');
                var pos = document.getElementById('carousel-pos');
                var badge = document.getElementById('carousel-badge');
                var prevBtn = document.getElementById('carousel-prev');
                var nextBtn = document.getElementById('carousel-next');
                var index = {{ $startIndex }};
                var total = slides.length;

                function show(i) {
                    if (total === 0) return;
                    // Boucle : après le dernier → premier, avant le premier → dernier
                    index = ((i % total) + total) % total;

                    for (var n = 0; n < total; n++) {
                        if (n === index) {
                            slides[n].classList.add('is-active');
                        } else {
                            slides[n].classList.remove('is-active');
                        }
                    }

                    var slide = slides[index];
                    var no = slide.getAttribute('data-no');
                    var revealed = slide.getAttribute('data-revealed') === '1';
                    var date = slide.getAttribute('data-date');

                    pos.textContent = String(index + 1);
                    meta.textContent = '#' + no + (date ? ' · ' + date : '');

                    badge.textContent = revealed ? 'Sorti' : 'En stock';
                    badge.className = 'badge ' + (revealed ? 'badge-done' : 'badge-ok');

                    prevBtn.disabled = false;
                    nextBtn.disabled = false;
                }

                prevBtn.onclick = function (e) {
                    e.preventDefault();
                    show(index - 1);
                };
                nextBtn.onclick = function (e) {
                    e.preventDefault();
                    show(index + 1);
                };

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'ArrowLeft') show(index - 1);
                    if (e.key === 'ArrowRight') show(index + 1);
                });

                show(index);
            })();
            </script>
        @endif
    </section>
@endsection
