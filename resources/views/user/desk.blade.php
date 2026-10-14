@extends('layouts.app')

@section('title', 'Vente tickets')

@section('content')
    <section class="panel">
        <h1>Ajouter un ticket</h1>
        <p class="lead">Chaque vente ajoute automatiquement le prix selon la durée.</p>

        @if ($dayAlreadyClosed && $todayClosing)
            <div class="alert alert-info" style="margin-bottom:1rem;">
                Journée déjà clôturée
                · {{ $todayClosing->tickets_count }} ticket(s)
                · {{ number_format($todayClosing->total_amount, 0, ',', ' ') }} F
                —
                <a href="{{ route('user.daily') }}">Voir la tâche journalière</a>
            </div>
        @endif

        <div class="duration-grid">
            @foreach ($lots as $key => $lot)
                <div class="duration-card {{ $lot['remaining'] > 0 && ! $dayAlreadyClosed ? 'has-stock' : 'empty-stock' }} {{ $selected === $key ? 'is-selected' : '' }}">
                    <div class="duration-card__title">{{ $lot['label'] }}</div>
                    <div class="duration-card__price">{{ $lot['price_label'] }}</div>
                    <div class="duration-card__stock">
                        <strong>{{ $lot['remaining'] }}</strong>
                        <span>en stock</span>
                    </div>

                    @if ($dayAlreadyClosed)
                        <button type="button" class="btn btn-ghost" style="width:100%;" disabled>Journée clôturée</button>
                    @elseif ($lot['remaining'] > 0)
                        <form action="{{ route('user.ticket.add') }}" method="POST"
                              onsubmit="return confirm('Voulez-vous vraiment vendre un ticket de {{ $lot['label'] }} ?');">
                            @csrf
                            <input type="hidden" name="duration_key" value="{{ $key }}">
                            <button type="submit" class="btn btn-primary" style="width:100%;">
                                Vendre {{ $lot['label'] }}
                            </button>
                        </form>
                    @else
                        <button type="button" class="btn btn-ghost" style="width:100%;" disabled>Rupture</button>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    @if ($lastTicket || $lastSale)
        <section class="panel">
            <h2>
                Dernière vente
                @if ($lastDuration)
                    · {{ \App\Support\TicketDurations::label($lastDuration) }}
                    · +{{ $lastSale?->price ?? \App\Support\TicketDurations::price($lastDuration) }} F
                @endif
            </h2>
            @if ($lastTicket)
                <div class="ticket-card ticket-card--image">
                    <div class="label">Ticket #{{ $lastTicket->ticket_no ?? $lastTicket->position }}</div>
                    <x-ticket-image :ticket="$lastTicket" />
                </div>
            @endif
        </section>
    @endif
@endsection
