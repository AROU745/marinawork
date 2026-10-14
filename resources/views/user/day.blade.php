@extends('layouts.app')

@section('title', 'Clôture '.$closing->business_date->format('d/m/Y'))

@section('content')
    <section class="panel">
        <h1>Fin de journée · {{ $closing->business_date->format('d/m/Y') }}</h1>
        <p class="lead">
            Clôturée le {{ $closing->closed_at->format('d/m/Y à H:i') }}.
        </p>

        <div class="stats">
            <div class="stat">
                <div class="n">{{ $closing->tickets_count }}</div>
                <div class="l">Tickets vendus</div>
            </div>
            <div class="stat">
                <div class="n">{{ number_format($closing->total_amount, 0, ',', ' ') }} F</div>
                <div class="l">Recette totale</div>
            </div>
            <div class="stat">
                <div class="n">{{ count($closing->breakdown ?? []) }}</div>
                <div class="l">Durées vendues</div>
            </div>
        </div>

        <h2>Détail par durée</h2>
        <table>
            <thead>
                <tr>
                    <th>Durée</th>
                    <th>Qté</th>
                    <th>Prix unit.</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($closing->breakdown ?? [] as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td>{{ $row['count'] }}</td>
                        <td>{{ number_format($row['unit_price'], 0, ',', ' ') }} F</td>
                        <td><strong>{{ number_format($row['amount'], 0, ',', ' ') }} F</strong></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td><strong>Total</strong></td>
                    <td><strong>{{ $closing->tickets_count }}</strong></td>
                    <td></td>
                    <td><strong>{{ number_format($closing->total_amount, 0, ',', ' ') }} F</strong></td>
                </tr>
            </tfoot>
        </table>

        <h2 style="margin-top:1.5rem;">Historique des ventes</h2>
        @if ($sales->isEmpty())
            <p class="empty">Aucune ligne de vente.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Heure</th>
                        <th>Durée</th>
                        <th>Prix</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sales as $i => $sale)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $sale->sold_at->format('H:i') }}</td>
                            <td>{{ $sale->durationLabel() }}</td>
                            <td>+{{ number_format($sale->price, 0, ',', ' ') }} F</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="actions">
            <a class="btn btn-ghost" href="{{ route('user.desk') }}">Mes tickets</a>
            <a class="btn btn-ghost" href="{{ route('user.daily') }}">Tâche journalière</a>
        </div>
    </section>

    <section class="panel">
        <h2>Autres jours</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Tickets</th>
                    <th>Recette</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($closings as $item)
                    <tr @if($item->id === $closing->id) style="background: rgba(61,214,140,0.08);" @endif>
                        <td>{{ $item->business_date->format('d/m/Y') }}</td>
                        <td>{{ $item->tickets_count }}</td>
                        <td><strong>{{ number_format($item->total_amount, 0, ',', ' ') }} F</strong></td>
                        <td>
                            <a href="{{ route('user.day', ['date' => $item->business_date->format('Y-m-d')]) }}">Voir</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endsection
