@extends('layouts.app')

@section('title', 'Ventes admin')

@section('content')
    <section class="panel">
        <div class="day-header">
            <div>
                <h1>Aperçu des ventes</h1>
                <p class="lead" style="margin-bottom:0;">
                    Tickets utilisés par vendeur, recette journalière, et meilleur jour.
                </p>
            </div>
            <form method="GET" action="{{ route('admin.sales') }}" class="visual-controls">
                <label>
                    Date
                    <input type="date" name="date" value="{{ $selectedDate }}"
                           style="border-radius:10px;border:1px solid var(--border);background:rgba(0,0,0,.25);color:var(--text);padding:.55rem .7rem;font:inherit;">
                </label>
                <button type="submit" class="btn btn-primary">Voir</button>
            </form>
        </div>

        <div class="stats" style="margin-top:1.25rem;">
            <div class="stat">
                <div class="n">{{ $dayTotalCount }}</div>
                <div class="l">Tickets utilisés · {{ $selectedDateLabel }}</div>
            </div>
            <div class="stat">
                <div class="n">{{ number_format($dayTotalAmount, 0, ',', ' ') }} F</div>
                <div class="l">Prix total journalier</div>
            </div>
            <div class="stat">
                <div class="n">{{ $byUser->count() }}</div>
                <div class="l">Vendeur(s) ce jour</div>
            </div>
        </div>
    </section>

    <section class="panel">
        <h2>Points forts</h2>
        <div class="duration-grid">
            <div class="duration-card has-stock">
                <div class="duration-card__title">Meilleur jour (tickets)</div>
                @if ($bestDayByTickets)
                    <div class="duration-card__stock">
                        <strong>{{ $bestDayByTickets->tickets_count }}</strong>
                        <span>tickets le {{ $bestDayByTickets->sold_on->format('d/m/Y') }}</span>
                    </div>
                    <div class="duration-card__meta">
                        Recette : {{ number_format($bestDayByTickets->total_amount, 0, ',', ' ') }} F
                        · {{ $bestDayByTickets->sellers_count }} vendeur(s)
                    </div>
                    <a class="btn btn-ghost" style="width:100%;" href="{{ route('admin.sales', ['date' => $bestDayByTickets->sold_on->format('Y-m-d')]) }}">
                        Voir ce jour
                    </a>
                @else
                    <p class="mini-empty">Pas encore de ventes.</p>
                @endif
            </div>

            <div class="duration-card has-stock">
                <div class="duration-card__title">Meilleur jour (recette)</div>
                @if ($bestDayByAmount)
                    <div class="duration-card__stock">
                        <strong>{{ number_format($bestDayByAmount->total_amount, 0, ',', ' ') }} F</strong>
                        <span>le {{ $bestDayByAmount->sold_on->format('d/m/Y') }}</span>
                    </div>
                    <div class="duration-card__meta">
                        {{ $bestDayByAmount->tickets_count }} ticket(s)
                    </div>
                    <a class="btn btn-ghost" style="width:100%;" href="{{ route('admin.sales', ['date' => $bestDayByAmount->sold_on->format('Y-m-d')]) }}">
                        Voir ce jour
                    </a>
                @else
                    <p class="mini-empty">Pas encore de ventes.</p>
                @endif
            </div>

            <div class="duration-card has-stock">
                <div class="duration-card__title">Top vendeur du jour</div>
                @if ($topSellerToday && $topSellerToday['user'])
                    <div class="duration-card__stock">
                        <strong>{{ $topSellerToday['tickets_count'] }}</strong>
                        <span>{{ $topSellerToday['user']->name }}</span>
                    </div>
                    <div class="duration-card__meta">
                        {{ number_format($topSellerToday['total_amount'], 0, ',', ' ') }} F le {{ $selectedDateLabel }}
                    </div>
                @else
                    <p class="mini-empty">Aucune vente ce jour.</p>
                @endif
            </div>

            <div class="duration-card has-stock">
                <div class="duration-card__title">Cumul global</div>
                <div class="duration-card__stock">
                    <strong>{{ $globalTickets }}</strong>
                    <span>tickets vendus au total</span>
                </div>
                <div class="duration-card__meta">
                    {{ number_format($globalAmount, 0, ',', ' ') }} F de recette globale
                    @if ($topSellerAllTime && $topSellerAllTime->user)
                        · Top : {{ $topSellerAllTime->user->name }}
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="panel">
        <h2>Tickets utilisés par vendeur · {{ $selectedDateLabel }}</h2>
        @if ($byUser->isEmpty())
            <p class="empty">Aucune vente ce jour-là.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Vendeur</th>
                        <th>Tickets utilisés</th>
                        <th>Prix total</th>
                        <th>Détail durées</th>
                        <th>État</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($byUser as $row)
                        <tr>
                            <td>
                                <strong>{{ $row['user']->name ?? '—' }}</strong>
                                <div style="color:var(--muted);font-size:.8rem;">{{ $row['user']->email ?? '' }}</div>
                            </td>
                            <td><strong>{{ $row['tickets_count'] }}</strong></td>
                            <td><strong>{{ number_format($row['total_amount'], 0, ',', ' ') }} F</strong></td>
                            <td style="font-size:.85rem;color:var(--muted);">
                                @foreach ($row['by_duration'] as $d)
                                    {{ $d['label'] }}×{{ $d['count'] }} ({{ number_format($d['amount'], 0, ',', ' ') }} F)
                                    @if (! $loop->last)<br>@endif
                                @endforeach
                            </td>
                            <td>
                                @if ($row['closed'])
                                    <span class="badge badge-done">Clôturé</span>
                                @else
                                    <span class="badge badge-ok">En cours</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td><strong>Total journalier</strong></td>
                        <td><strong>{{ $dayTotalCount }}</strong></td>
                        <td><strong>{{ number_format($dayTotalAmount, 0, ',', ' ') }} F</strong></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </section>

    <section class="panel">
        <h2>Historique général · toutes les dates</h2>
        <p class="lead" style="margin-top:0;">Synthèse de toutes les ventes, toutes dates confondues.</p>

        <div class="stats" style="margin-bottom:1.25rem;">
            <div class="stat">
                <div class="n">{{ $globalTickets }}</div>
                <div class="l">Tickets vendus</div>
            </div>
            <div class="stat">
                <div class="n">{{ number_format($globalAmount, 0, ',', ' ') }} F</div>
                <div class="l">Recette totale</div>
            </div>
            <div class="stat">
                <div class="n">{{ $globalDays }}</div>
                <div class="l">Jour(s) de vente</div>
            </div>
            <div class="stat">
                <div class="n">{{ $globalSellers }}</div>
                <div class="l">Vendeur(s)</div>
            </div>
        </div>

        @if ($generalByDuration->isEmpty())
            <p class="empty">Aucune vente enregistrée.</p>
        @else
            <h3 style="margin:0 0 .75rem;font-size:1rem;">Détail par durée (toutes dates)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Durée</th>
                        <th>Tickets</th>
                        <th>Prix unit.</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($generalByDuration as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td><strong>{{ $row['count'] }}</strong></td>
                            <td>{{ number_format($row['unit_price'], 0, ',', ' ') }} F</td>
                            <td><strong>{{ number_format($row['amount'], 0, ',', ' ') }} F</strong></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td><strong>Total général</strong></td>
                        <td><strong>{{ $globalTickets }}</strong></td>
                        <td></td>
                        <td><strong>{{ number_format($globalAmount, 0, ',', ' ') }} F</strong></td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </section>

    <section class="panel">
        <h2>Historique journalier (prix & tickets)</h2>
        @if ($dailyHistory->isEmpty())
            <p class="empty">Aucun historique.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Tickets vendus</th>
                        <th>Prix total</th>
                        <th>Vendeurs</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dailyHistory as $day)
                        @php
                            $isBest = $bestDayByTickets && $day->sold_on->equalTo($bestDayByTickets->sold_on);
                            $isSelected = $day->sold_on->format('Y-m-d') === $selectedDate;
                        @endphp
                        <tr @if($isSelected || $isBest) style="background:rgba(61,214,140,.08);" @endif>
                            <td>
                                {{ $day->sold_on->format('d/m/Y') }}
                                @if ($isBest)
                                    <span class="badge badge-ok">Record tickets</span>
                                @endif
                            </td>
                            <td><strong>{{ $day->tickets_count }}</strong></td>
                            <td><strong>{{ number_format($day->total_amount, 0, ',', ' ') }} F</strong></td>
                            <td>{{ $day->sellers_count }}</td>
                            <td>
                                <a href="{{ route('admin.sales', ['date' => $day->sold_on->format('Y-m-d')]) }}">Détail</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td><strong>Total toutes dates</strong></td>
                        <td><strong>{{ $globalTickets }}</strong></td>
                        <td><strong>{{ number_format($globalAmount, 0, ',', ' ') }} F</strong></td>
                        <td>{{ $globalSellers }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </section>

    <section class="panel">
        <h2>Cumul tickets par vendeur · toutes dates</h2>
        @if ($sellerTotals->isEmpty())
            <p class="empty">Aucune vente enregistrée.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Vendeur</th>
                        <th>Tickets utilisés</th>
                        <th>Recette totale</th>
                        <th>Détail durées</th>
                        <th>Jours</th>
                        <th>Dernière vente</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sellerTotals as $row)
                        <tr>
                            <td>
                                <strong>{{ $row->user->name ?? '—' }}</strong>
                                <div style="color:var(--muted);font-size:.8rem;">{{ $row->user->email ?? '' }}</div>
                            </td>
                            <td><strong>{{ $row->tickets_count }}</strong></td>
                            <td><strong>{{ number_format($row->total_amount, 0, ',', ' ') }} F</strong></td>
                            <td style="font-size:.85rem;color:var(--muted);">
                                @forelse ($row->by_duration as $d)
                                    {{ $d['label'] }}×{{ $d['count'] }} ({{ number_format($d['amount'], 0, ',', ' ') }} F)
                                    @if (! $loop->last)<br>@endif
                                @empty
                                    —
                                @endforelse
                            </td>
                            <td>{{ $row->days_count }}</td>
                            <td>{{ $row->last_sale_on?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td><strong>Total général</strong></td>
                        <td><strong>{{ $globalTickets }}</strong></td>
                        <td><strong>{{ number_format($globalAmount, 0, ',', ' ') }} F</strong></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </section>

    <section class="panel">
        <h2>Clôtures des vendeurs</h2>
        @if ($closings->isEmpty())
            <p class="empty">Aucune clôture pour le moment.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Vendeur</th>
                        <th>Tickets</th>
                        <th>Recette</th>
                        <th>Clôturée à</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($closings as $closing)
                        <tr>
                            <td>{{ $closing->business_date->format('d/m/Y') }}</td>
                            <td>{{ $closing->user->name ?? '—' }}</td>
                            <td>{{ $closing->tickets_count }}</td>
                            <td><strong>{{ number_format($closing->total_amount, 0, ',', ' ') }} F</strong></td>
                            <td>{{ $closing->closed_at->format('H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <div class="actions">
        <a class="btn btn-ghost" href="{{ route('tickets.index') }}">Retour lots</a>
    </div>
@endsection
