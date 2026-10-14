@extends('layouts.app')

@section('title', 'Tâche journalière')

@section('content')
    <section class="panel">
        <div class="day-header">
            <div>
                <h1>Tâche journalière</h1>
                @php
                    $jours = ['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'];
                    $jourDate = \Illuminate\Support\Carbon::parse($today);
                    $jourLabel = $jours[$jourDate->dayOfWeek].' '.$jourDate->format('d/m/Y');
                @endphp
                <p class="lead" style="margin-bottom:0;">
                    Date automatique : <strong>{{ $jourLabel }}</strong>
                </p>
            </div>
        </div>

        <nav class="task-tabs" aria-label="Onglets tâche journalière">
            <a href="{{ route('user.daily', ['onglet' => 'cloture']) }}"
               class="task-tab {{ $tab === 'cloture' ? 'is-active' : '' }}">Clôture</a>
            <a href="{{ route('user.daily', ['onglet' => 'livraison']) }}"
               class="task-tab {{ $tab === 'livraison' ? 'is-active' : '' }}">Livraison</a>
            <a href="{{ route('user.daily', ['onglet' => 'clientele']) }}"
               class="task-tab {{ $tab === 'clientele' ? 'is-active' : '' }}">Clientèle</a>
        </nav>
    </section>

    @if ($tab === 'cloture')
        <section class="panel">
            <div class="day-header">
                <div>
                    <h2 style="margin:0;">Clôture du jour</h2>
                </div>
                <div class="day-totals">
                    <div class="stat">
                        <div class="n">{{ $todayTotalCount }}</div>
                        <div class="l">Tickets vendus</div>
                    </div>
                    <div class="stat">
                        <div class="n">{{ number_format($todayTotalAmount, 0, ',', ' ') }} F</div>
                        <div class="l">Recette du jour</div>
                    </div>
                </div>
            </div>

            @if ($dayAlreadyClosed && $todayClosing)
                <div class="alert alert-info" style="margin-top:1rem;">
                    Journée du <strong>{{ $todayClosing->business_date->format('d/m/Y') }}</strong> déjà clôturée
                    · {{ $todayClosing->tickets_count }} ticket(s)
                    · {{ number_format($todayClosing->total_amount, 0, ',', ' ') }} F
                    —
                    <a href="{{ route('user.day', ['date' => $today]) }}">Voir le rapport</a>
                </div>
            @endif

            <div style="margin-top:1.25rem;">
                <h3 style="margin:0 0 .75rem;font-size:1rem;">Détail · {{ \Illuminate\Support\Carbon::parse($today)->format('d/m/Y') }}</h3>
                @if ($todayTotalCount === 0)
                    <p class="empty" style="padding:1rem 0;">Aucune vente pour aujourd’hui.</p>
                @else
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
                            @foreach ($todayByDuration as $row)
                                @if ($row['count'] > 0)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td><strong>{{ $row['count'] }}</strong></td>
                                        <td>{{ number_format($row['unit_price'], 0, ',', ' ') }} F</td>
                                        <td><strong>{{ number_format($row['amount'], 0, ',', ' ') }} F</strong></td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td><strong>Total du {{ \Illuminate\Support\Carbon::parse($today)->format('d/m/Y') }}</strong></td>
                                <td><strong>{{ $todayTotalCount }}</strong></td>
                                <td></td>
                                <td><strong>{{ number_format($todayTotalAmount, 0, ',', ' ') }} F</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                @endif
            </div>

            @if (! $dayAlreadyClosed && $openSales->count() > 0)
                <div class="actions" style="justify-content:center;margin-top:1.25rem;">
                    <form action="{{ route('user.day.close') }}" method="POST"
                          onsubmit="return confirm('Clôturer la journée du {{ \Illuminate\Support\Carbon::parse($today)->format('d/m/Y') }} ?\n{{ $todayTotalCount }} ticket(s) · {{ $todayTotalAmount }} F');">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-lg">Fin de journée</button>
                    </form>
                </div>
            @elseif (! $dayAlreadyClosed)
                <p class="lead" style="margin-top:1.25rem;text-align:center;">
                    Vendez des tickets dans <a href="{{ route('user.desk') }}">Mes tickets</a> pour pouvoir clôturer la journée.
                </p>
            @endif
        </section>

        <section class="panel">
            <h2>Historique des clôtures</h2>
            @if ($closings->isEmpty())
                <p class="empty">Aucune journée clôturée pour le moment.</p>
            @else
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
                        @foreach ($closings as $closing)
                            <tr @if($closing->business_date->format('Y-m-d') === $today) style="background:rgba(61,214,140,.08);" @endif>
                                <td>{{ $closing->business_date->format('d/m/Y') }}</td>
                                <td>{{ $closing->tickets_count }}</td>
                                <td><strong>{{ number_format($closing->total_amount, 0, ',', ' ') }} F</strong></td>
                                <td>
                                    <a href="{{ route('user.day', ['date' => $closing->business_date->format('Y-m-d')]) }}">Voir</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endif

    @if ($tab === 'livraison')
        <section class="panel">
            <h2>Nouvelle livraison</h2>
            <p class="lead">Suivi des colis à livrer (séparé de la clientèle).</p>

            <form action="{{ route('user.deliveries.store') }}" method="POST" class="task-form">
                @csrf
                <div class="task-form__grid">
                    <label>
                        Client enregistré (optionnel)
                        <select name="customer_id" id="delivery-customer">
                            <option value="">— Aucun —</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}"
                                    data-name="{{ $customer->name }}"
                                    data-phone="{{ $customer->phone }}"
                                    data-address="{{ $customer->address }}">
                                    {{ $customer->name }}
                                    @if ($customer->phone) · {{ $customer->phone }} @endif
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Destinataire *
                        <input type="text" name="recipient_name" id="delivery-name" value="{{ old('recipient_name') }}" required>
                    </label>
                    <label>
                        Téléphone
                        <input type="text" name="phone" id="delivery-phone" value="{{ old('phone') }}">
                    </label>
                    <label>
                        Adresse / lieu de livraison
                        <input type="text" name="address" id="delivery-address" value="{{ old('address') }}">
                    </label>
                    <label>
                        Lieu de réception du colis
                        <input type="text" name="reception_place" value="{{ old('reception_place') }}" placeholder="Ex. agence, dépôt, boutique…">
                    </label>
                    <label>
                        Lien GPS (réception) — optionnel
                        <input type="text" name="reception_gps_url" value="{{ old('reception_gps_url') }}"
                               placeholder="Coller le lien Google Maps / GPS (facultatif)">
                    </label>
                    <label>
                        Date & heure d’ajout
                        <input type="text" value="{{ now()->format('d/m/Y H:i') }} — automatique à l’enregistrement" disabled>
                    </label>
                    <label>
                        Date & heure de validation
                        <input type="text" value="Automatique au clic sur « Livrée »" disabled>
                    </label>
                    <label class="task-form__full">
                        Colis / description
                        <input type="text" name="notes" value="{{ old('notes') }}" placeholder="Ex. colis, paquet…">
                    </label>
                </div>
                <div class="actions" style="margin-top:1rem;">
                    <button type="submit" class="btn btn-primary">Enregistrer la livraison</button>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="day-header">
                <h2 style="margin:0;">Historique · en attente</h2>
                <div class="stat" style="min-width:auto;padding:.55rem .85rem;">
                    <div class="n" style="font-size:1.1rem;">{{ $pendingDeliveries->count() }}</div>
                    <div class="l">colis</div>
                </div>
            </div>
            @if ($pendingDeliveries->isEmpty())
                <p class="empty" style="margin-top:1rem;">Aucune livraison en attente.</p>
            @else
                <table style="margin-top:1rem;">
                    <thead>
                        <tr>
                            <th>Ajout</th>
                            <th>Destinataire</th>
                            <th>Contact</th>
                            <th>Réception / GPS</th>
                            <th>Colis</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendingDeliveries as $delivery)
                            <tr @if($delivery->delivery_date->format('Y-m-d') === $today) style="background:rgba(61,214,140,.08);" @endif>
                                <td>
                                    <strong>{{ $delivery->created_at->format('d/m/Y') }}</strong>
                                    <div style="color:var(--accent);font-size:.85rem;font-weight:700;">{{ $delivery->created_at->format('H:i') }}</div>
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
                                <td style="white-space:nowrap;">
                                    <form action="{{ route('user.deliveries.done', $delivery) }}" method="POST" style="display:inline;"
                                          onsubmit="return confirm('Confirmer la livraison ?\nDate et heure {{ now()->format('d/m/Y H:i') }} seront enregistrées automatiquement.');">
                                        @csrf
                                        <button type="submit" class="btn btn-primary" style="padding:.35rem .7rem;font-size:.8rem;">Livrée</button>
                                    </form>
                                    <form action="{{ route('user.deliveries.destroy', $delivery) }}" method="POST" style="display:inline;"
                                          onsubmit="return confirm('Supprimer cette livraison ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost" style="padding:.35rem .6rem;font-size:.8rem;">Suppr.</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <section class="panel">
            <div class="day-header">
                <h2 style="margin:0;">Historique · effectuées</h2>
                <div class="stat" style="min-width:auto;padding:.55rem .85rem;">
                    <div class="n" style="font-size:1.1rem;">{{ $doneDeliveries->count() }}</div>
                    <div class="l">colis</div>
                </div>
            </div>
            @if ($doneDeliveries->isEmpty())
                <p class="empty" style="margin-top:1rem;">Aucune livraison effectuée pour le moment.</p>
            @else
                <table style="margin-top:1rem;">
                    <thead>
                        <tr>
                            <th>Ajout</th>
                            <th>Destinataire</th>
                            <th>Contact</th>
                            <th>Réception / GPS</th>
                            <th>Colis</th>
                            <th>Validation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($doneDeliveries as $delivery)
                            <tr>
                                <td>
                                    <strong>{{ $delivery->created_at->format('d/m/Y') }}</strong>
                                    <div style="color:var(--muted);font-size:.85rem;">{{ $delivery->created_at->format('H:i') }}</div>
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
                                    <span class="badge badge-done">Effectuée</span>
                                    @if ($delivery->delivered_at)
                                        <div style="color:var(--accent);font-weight:700;">{{ $delivery->delivered_at->format('d/m/Y') }}</div>
                                        <div style="color:var(--accent);font-size:.85rem;font-weight:700;">{{ $delivery->delivered_at->format('H:i') }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        <script>
            (function () {
                var select = document.getElementById('delivery-customer');
                if (!select) return;
                select.addEventListener('change', function () {
                    var opt = select.options[select.selectedIndex];
                    if (!opt || !opt.value) return;
                    var name = document.getElementById('delivery-name');
                    var phone = document.getElementById('delivery-phone');
                    var address = document.getElementById('delivery-address');
                    if (name && !name.value) name.value = opt.getAttribute('data-name') || '';
                    if (phone && !phone.value) phone.value = opt.getAttribute('data-phone') || '';
                    if (address && !address.value) address.value = opt.getAttribute('data-address') || '';
                });
            })();
        </script>
    @endif

    @if ($tab === 'clientele')
        <section class="panel">
            <h2>Nouveau client</h2>
            <p class="lead">Fiche clientèle distincte des livraisons.</p>

            <form action="{{ route('user.customers.store') }}" method="POST" class="task-form">
                @csrf
                <div class="task-form__grid">
                    <label>
                        Nom *
                        <input type="text" name="name" value="{{ old('name') }}" required>
                    </label>
                    <label>
                        Téléphone
                        <input type="text" name="phone" value="{{ old('phone') }}">
                    </label>
                    <label class="task-form__full">
                        Adresse
                        <input type="text" name="address" value="{{ old('address') }}">
                    </label>
                    <label class="task-form__full">
                        Notes
                        <textarea name="notes" rows="2">{{ old('notes') }}</textarea>
                    </label>
                </div>
                <div class="actions" style="margin-top:1rem;">
                    <button type="submit" class="btn btn-primary">Enregistrer le client</button>
                </div>
            </form>
        </section>

        <section class="panel">
            <h2>Liste clientèle</h2>
            @if ($customers->isEmpty())
                <p class="empty">Aucun client enregistré.</p>
            @else
                <table>
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Téléphone</th>
                            <th>Adresse</th>
                            <th>Notes</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $customer)
                            <tr>
                                <td><strong>{{ $customer->name }}</strong></td>
                                <td>{{ $customer->phone ?: '—' }}</td>
                                <td>{{ $customer->address ?: '—' }}</td>
                                <td style="font-size:.85rem;color:var(--muted);">{{ $customer->notes ?: '—' }}</td>
                                <td>
                                    <form action="{{ route('user.customers.destroy', $customer) }}" method="POST"
                                          onsubmit="return confirm('Supprimer ce client ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-ghost" style="padding:.35rem .6rem;font-size:.8rem;">Suppr.</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endif
@endsection
