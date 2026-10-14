@extends('layouts.app')

@section('title', 'Admin tickets')

@section('content')
    <section class="panel" style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;justify-content:space-between;">
        <div>
            <h1 style="margin-bottom:.35rem;">Espace admin</h1>
            <p class="lead" style="margin:0;">Lots de tickets et suivi des ventes vendeurs.</p>
        </div>
        <a class="btn btn-primary" href="{{ route('admin.sales') }}">Voir les ventes / recettes</a>
    </section>

    <section class="panel">
        <h1>Importer un lot de tickets</h1>
        <p class="lead">
            Choisissez la durée du lot (15 min, 30 min, 1 h…), puis chargez le PDF.
            Les tickets seront classés dans ce lot.
        </p>

        <form action="{{ route('tickets.upload') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="upload-row">
                <label class="field">
                    Durée du lot
                    <select name="duration_key" required>
                        <option value="">— Choisir —</option>
                        @foreach ($durations as $key => $label)
                            <option value="{{ $key }}" @selected(old('duration_key') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <input type="file" name="pdf" accept="application/pdf,.pdf" required>
                <button type="submit" class="btn btn-primary">Scanner le PDF</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <h2>Lots par durée</h2>
        <div class="duration-grid">
            @foreach ($batchesByDuration as $key => $group)
                <div class="duration-card {{ $group['remaining'] > 0 ? 'has-stock' : 'empty-stock' }}">
                    <div class="duration-card__title">{{ $group['label'] }}</div>
                    <div class="duration-card__stock">
                        <strong>{{ $group['remaining'] }}</strong>
                        <span>restants</span>
                    </div>
                    <div class="duration-card__meta">{{ $group['batches']->count() }} lot(s) · {{ $group['total'] }} tickets</div>

                    @if ($group['batches']->isEmpty())
                        <p class="mini-empty">Aucun PDF pour cette durée</p>
                    @else
                        <ul class="duration-card__list">
                            @foreach ($group['batches']->take(3) as $batch)
                                <li>
                                    @if ($batch->isPendingVisualScan())
                                        <a href="{{ route('tickets.visual', $batch) }}">{{ $batch->original_filename }}</a>
                                        <span class="badge badge-ok">À découper</span>
                                    @else
                                        <a href="{{ route('tickets.show', $batch) }}">{{ $batch->original_filename }}</a>
                                        <span>{{ $batch->remainingCount() }} restants</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <section class="panel">
        <h2>Historique des lots</h2>
        @if ($batches->isEmpty())
            <p class="empty">Aucun PDF importé pour le moment.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Durée</th>
                        <th>Fichier</th>
                        <th>Progression</th>
                        <th>État</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($batches as $batch)
                        <tr>
                            <td><strong>{{ $batch->durationLabel() }}</strong></td>
                            <td>
                                <a href="{{ route('tickets.show', $batch) }}">{{ $batch->original_filename }}</a>
                                <div style="color: var(--muted); font-size: 0.8rem;">{{ $batch->created_at->format('d/m/Y H:i') }}</div>
                            </td>
                            <td>{{ $batch->current_index }} / {{ $batch->total_tickets }}</td>
                            <td>
                                @if ($batch->isPendingVisualScan())
                                    <span class="badge badge-ok">À découper</span>
                                @elseif ($batch->isExhausted())
                                    <span class="badge badge-done">Terminé</span>
                                @else
                                    <span class="badge badge-ok">En cours</span>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    @if ($batch->isPendingVisualScan())
                                        <a href="{{ route('tickets.visual', $batch) }}">Découper</a>
                                    @else
                                        <a href="{{ route('tickets.show', $batch) }}">Ouvrir</a>
                                    @endif
                                    <form action="{{ route('tickets.destroy', $batch) }}" method="POST" onsubmit="return confirm('Supprimer ce lot {{ $batch->durationLabel() }} ?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="link-danger">Supprimer</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
