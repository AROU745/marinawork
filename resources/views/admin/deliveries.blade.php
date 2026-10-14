@extends('layouts.app')

@section('title', 'Clients & livraisons')

@section('content')
    <section class="panel">
        <div class="day-header">
            <div>
                <h1>Clients & livraisons</h1>
                <p class="lead" style="margin-bottom:0;">
                    Vue admin : tous les vendeurs, tous les clients, toutes les livraisons.
                </p>
            </div>
        </div>

        <div class="stats" style="margin-top:1.25rem;">
            <div class="stat">
                <div class="n">{{ $pendingCount }}</div>
                <div class="l">En attente</div>
            </div>
            <div class="stat">
                <div class="n">{{ $doneCount }}</div>
                <div class="l">Effectuées</div>
            </div>
            <div class="stat">
                <div class="n">{{ $customersCount }}</div>
                <div class="l">Clients</div>
            </div>
        </div>

        <nav class="task-tabs" aria-label="Onglets admin livraisons">
            <a href="{{ route('admin.deliveries', ['onglet' => 'livraisons']) }}"
               class="task-tab {{ $tab === 'livraisons' ? 'is-active' : '' }}">Livraisons</a>
            <a href="{{ route('admin.deliveries', ['onglet' => 'clients']) }}"
               class="task-tab {{ $tab === 'clients' ? 'is-active' : '' }}">Clients</a>
        </nav>
    </section>

    @if ($tab === 'livraisons')
        <section class="panel">
            <div class="day-header" style="align-items:center;">
                <h2 style="margin:0;">Historique des livraisons</h2>
                <div class="task-tabs" style="margin:0;padding:0;border:0;">
                    <a href="{{ route('admin.deliveries', ['onglet' => 'livraisons', 'statut' => 'tous']) }}"
                       class="task-tab {{ $status === 'tous' ? 'is-active' : '' }}">Toutes</a>
                    <a href="{{ route('admin.deliveries', ['onglet' => 'livraisons', 'statut' => 'en_attente']) }}"
                       class="task-tab {{ $status === 'en_attente' ? 'is-active' : '' }}">En attente</a>
                    <a href="{{ route('admin.deliveries', ['onglet' => 'livraisons', 'statut' => 'livree']) }}"
                       class="task-tab {{ $status === 'livree' ? 'is-active' : '' }}">Effectuées</a>
                </div>
            </div>

            @if ($deliveries->isEmpty())
                <p class="empty" style="margin-top:1rem;">Aucune livraison pour ce filtre.</p>
            @else
                <table style="margin-top:1rem;">
                    <thead>
                        <tr>
                            <th>Ajout</th>
                            <th>Vendeur</th>
                            <th>Destinataire</th>
                            <th>Contact</th>
                            <th>Réception / GPS</th>
                            <th>Colis</th>
                            <th>Validation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deliveries as $delivery)
                            <tr>
                                <td>
                                    <strong>{{ $delivery->created_at->format('d/m/Y') }}</strong>
                                    <div style="color:var(--muted);font-size:.85rem;">{{ $delivery->created_at->format('H:i') }}</div>
                                </td>
                                <td>
                                    <strong>{{ $delivery->user->name ?? '—' }}</strong>
                                    <div style="color:var(--muted);font-size:.8rem;">{{ $delivery->user->email ?? '' }}</div>
                                </td>
                                <td>
                                    <strong>{{ $delivery->recipient_name }}</strong>
                                    @if ($delivery->address)
                                        <div style="color:var(--muted);font-size:.8rem;">{{ $delivery->address }}</div>
                                    @endif
                                </td>
                                <td>{{ $delivery->phone ?: '—' }}</td>
                                <td>
                                    {{ $delivery->reception_place ?: '—' }}
                                    @if ($delivery->gpsUrl())
                                        <div><a href="{{ $delivery->gpsUrl() }}" target="_blank" rel="noopener">Ouvrir GPS</a></div>
                                    @endif
                                </td>
                                <td style="font-size:.85rem;color:var(--muted);">{{ $delivery->notes ?: '—' }}</td>
                                <td>
                                    @if ($delivery->status === 'livree')
                                        <span class="badge badge-done">Effectuée</span>
                                        @if ($delivery->delivered_at)
                                            <div style="color:var(--accent);font-weight:700;">{{ $delivery->delivered_at->format('d/m/Y') }}</div>
                                            <div style="color:var(--accent);font-size:.85rem;font-weight:700;">{{ $delivery->delivered_at->format('H:i') }}</div>
                                        @endif
                                    @elseif ($delivery->status === 'annulee')
                                        <span class="badge badge-warn">Annulée</span>
                                    @else
                                        <span class="badge badge-ok">En attente</span>
                                        <div style="color:var(--muted);font-size:.75rem;">Pas encore validée</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endif

    @if ($tab === 'clients')
        <section class="panel">
            <h2>Tous les clients</h2>
            @if ($customers->isEmpty())
                <p class="empty">Aucun client enregistré.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Téléphone</th>
                            <th>Adresse</th>
                            <th>Vendeur</th>
                            <th>Livraisons</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $customer)
                            <tr>
                                <td><strong>{{ $customer->name }}</strong></td>
                                <td>{{ $customer->phone ?: '—' }}</td>
                                <td>{{ $customer->address ?: '—' }}</td>
                                <td>
                                    <strong>{{ $customer->user->name ?? '—' }}</strong>
                                    <div style="color:var(--muted);font-size:.8rem;">{{ $customer->user->email ?? '' }}</div>
                                </td>
                                <td><strong>{{ $customer->deliveries_count }}</strong></td>
                                <td style="font-size:.85rem;color:var(--muted);">{{ $customer->notes ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endif

    <div class="actions">
        <a class="btn btn-ghost" href="{{ route('admin.sales') }}">Ventes</a>
        <a class="btn btn-ghost" href="{{ route('tickets.index') }}">Lots</a>
    </div>
@endsection
