<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DayClosing;
use App\Models\Delivery;
use App\Models\Ticket;
use App\Models\TicketBatch;
use App\Models\TicketSale;
use App\Support\TicketDurations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserDeskController extends Controller
{
    public function desk(Request $request): View
    {
        $user = Auth::user();
        $today = now()->toDateString();

        $selected = $request->query('duree');
        if (! TicketDurations::isValid($selected)) {
            $selected = null;
        }

        $lots = [];
        foreach (TicketDurations::all() as $key => $label) {
            $remaining = TicketBatch::query()
                ->available()
                ->forDuration($key)
                ->get()
                ->sum(fn (TicketBatch $b) => $b->remainingCount());

            $lots[$key] = [
                'label' => $label,
                'price' => TicketDurations::price($key),
                'price_label' => TicketDurations::priceLabel($key),
                'remaining' => $remaining,
            ];
        }

        // Toutes les ventes du jour (même après clôture) — comme l'admin
        $todaySales = TicketSale::query()
            ->where('user_id', $user->id)
            ->whereDate('sold_on', $today)
            ->orderByDesc('sold_at')
            ->get();

        $openSales = $todaySales->whereNull('day_closing_id')->values();

        $todayByDuration = [];
        foreach (TicketDurations::keys() as $key) {
            $group = $todaySales->where('duration_key', $key);
            $todayByDuration[$key] = [
                'label' => TicketDurations::label($key),
                'unit_price' => TicketDurations::price($key),
                'count' => $group->count(),
                'amount' => (int) $group->sum('price'),
            ];
        }

        $todayTotalCount = $todaySales->count();
        $todayTotalAmount = (int) $todaySales->sum('price');

        $todayClosing = DayClosing::query()
            ->where('user_id', $user->id)
            ->whereDate('business_date', $today)
            ->first();

        $dayAlreadyClosed = $todayClosing !== null;

        $lastTicket = null;
        $lastDuration = null;
        $lastSale = $todaySales->first();

        if ($lastSale) {
            $lastTicket = $lastSale->ticket_id
                ? Ticket::query()->find($lastSale->ticket_id)
                : null;
            $lastDuration = $lastSale->duration_key;
        }

        $closings = DayClosing::query()
            ->where('user_id', $user->id)
            ->latest('business_date')
            ->limit(30)
            ->get();

        return view('user.desk', [
            'lots' => $lots,
            'selected' => $selected,
            'lastTicket' => $lastTicket,
            'lastDuration' => $lastDuration,
            'lastSale' => $lastSale,
            'today' => $today,
            'todaySales' => $todaySales,
            'openSales' => $openSales,
            'todayByDuration' => $todayByDuration,
            'todayTotalCount' => $todayTotalCount,
            'todayTotalAmount' => $todayTotalAmount,
            'dayAlreadyClosed' => $dayAlreadyClosed,
            'todayClosing' => $todayClosing,
            'closings' => $closings,
            'durations' => TicketDurations::all(),
        ]);
    }

    /**
     * Tâche journalière : onglets Clôture / Livraison / Clientèle.
     */
    public function dailyTask(Request $request): View
    {
        $user = Auth::user();
        $today = now()->toDateString();
        $tab = $request->query('onglet', 'cloture');
        if (! in_array($tab, ['cloture', 'livraison', 'clientele'], true)) {
            $tab = 'cloture';
        }

        $todaySales = TicketSale::query()
            ->where('user_id', $user->id)
            ->whereDate('sold_on', $today)
            ->orderByDesc('sold_at')
            ->get();

        $openSales = $todaySales->whereNull('day_closing_id')->values();

        $todayByDuration = [];
        foreach (TicketDurations::keys() as $key) {
            $group = $todaySales->where('duration_key', $key);
            $todayByDuration[$key] = [
                'label' => TicketDurations::label($key),
                'unit_price' => TicketDurations::price($key),
                'count' => $group->count(),
                'amount' => (int) $group->sum('price'),
            ];
        }

        $todayClosing = DayClosing::query()
            ->where('user_id', $user->id)
            ->whereDate('business_date', $today)
            ->first();

        $closings = DayClosing::query()
            ->where('user_id', $user->id)
            ->latest('business_date')
            ->limit(30)
            ->get();

        $customers = Customer::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(100)
            ->get();

        $deliveries = Delivery::query()
            ->with('customer')
            ->where('user_id', $user->id)
            ->latest('delivery_date')
            ->latest('id')
            ->limit(200)
            ->get();

        $pendingDeliveries = $deliveries
            ->where('status', Delivery::STATUS_PENDING)
            ->values();

        $doneDeliveries = $deliveries
            ->where('status', Delivery::STATUS_DONE)
            ->values();

        $todayDeliveries = $deliveries->filter(
            fn (Delivery $d) => $d->delivery_date->format('Y-m-d') === $today
        )->values();

        return view('user.daily', [
            'tab' => $tab,
            'today' => $today,
            'todaySales' => $todaySales,
            'openSales' => $openSales,
            'todayByDuration' => $todayByDuration,
            'todayTotalCount' => $todaySales->count(),
            'todayTotalAmount' => (int) $todaySales->sum('price'),
            'dayAlreadyClosed' => $todayClosing !== null,
            'todayClosing' => $todayClosing,
            'closings' => $closings,
            'customers' => $customers,
            'deliveries' => $deliveries,
            'pendingDeliveries' => $pendingDeliveries,
            'doneDeliveries' => $doneDeliveries,
            'todayDeliveries' => $todayDeliveries,
            'durations' => TicketDurations::all(),
        ]);
    }

    public function storeCustomer(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.required' => 'Le nom du client est obligatoire.',
        ]);

        Customer::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()
            ->route('user.daily', ['onglet' => 'clientele'])
            ->with('success', 'Client enregistré.');
    }

    public function destroyCustomer(Customer $customer): RedirectResponse
    {
        if ($customer->user_id !== Auth::id()) {
            abort(403);
        }

        $customer->delete();

        return redirect()
            ->route('user.daily', ['onglet' => 'clientele'])
            ->with('success', 'Client supprimé.');
    }

    public function storeDelivery(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $today = now()->toDateString();

        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'reception_place' => ['nullable', 'string', 'max:255'],
            'reception_gps_url' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'recipient_name.required' => 'Le destinataire est obligatoire.',
        ]);

        if (! empty($data['customer_id'])) {
            $customer = Customer::query()
                ->where('user_id', $user->id)
                ->where('id', $data['customer_id'])
                ->first();

            if (! $customer) {
                return redirect()
                    ->route('user.daily', ['onglet' => 'livraison'])
                    ->with('info', 'Client introuvable.');
            }
        } else {
            $customer = null;
        }

        $gps = trim((string) ($data['reception_gps_url'] ?? ''));
        if ($gps !== '' && ! preg_match('#^(https?://|geo:)#i', $gps)) {
            $gps = 'https://'.$gps;
        }

        Delivery::create([
            'user_id' => $user->id,
            'customer_id' => $customer?->id,
            'recipient_name' => $data['recipient_name'],
            'phone' => $data['phone'] ?? $customer?->phone,
            'address' => $data['address'] ?? $customer?->address,
            'reception_place' => $data['reception_place'] ?? null,
            'reception_gps_url' => $gps !== '' ? $gps : null,
            'duration_key' => null,
            'quantity' => 1,
            'status' => Delivery::STATUS_PENDING,
            'notes' => $data['notes'] ?? null,
            'delivery_date' => $today,
        ]);

        return redirect()
            ->route('user.daily', ['onglet' => 'livraison'])
            ->with('success', 'Livraison ajoutée le '.now()->format('d/m/Y').' à '.now()->format('H:i').'.');
    }

    public function markDeliveryDone(Delivery $delivery): RedirectResponse
    {
        if ($delivery->user_id !== Auth::id()) {
            abort(403);
        }

        $deliveredAt = now();
        $delivery->update([
            'status' => Delivery::STATUS_DONE,
            'delivered_at' => $deliveredAt,
        ]);

        return redirect()
            ->route('user.daily', ['onglet' => 'livraison'])
            ->with(
                'success',
                'Colis validé le '.$deliveredAt->format('d/m/Y').' à '.$deliveredAt->format('H:i').' (automatique).'
            );
    }

    public function destroyDelivery(Delivery $delivery): RedirectResponse
    {
        if ($delivery->user_id !== Auth::id()) {
            abort(403);
        }

        $delivery->delete();

        return redirect()
            ->route('user.daily', ['onglet' => 'livraison'])
            ->with('success', 'Livraison supprimée.');
    }

    public function addTicket(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $today = now()->toDateString();

        if (DayClosing::query()->where('user_id', $user->id)->whereDate('business_date', $today)->exists()) {
            return redirect()
                ->route('user.desk')
                ->with('info', 'La journée est déjà clôturée. Revenez demain pour vendre.');
        }

        $data = $request->validate([
            'duration_key' => ['required', TicketDurations::validationRule()],
        ], [
            'duration_key.required' => 'Choisissez une durée.',
            'duration_key.in' => 'Durée invalide.',
        ]);

        $durationKey = $data['duration_key'];
        $price = TicketDurations::price($durationKey);

        $batch = TicketBatch::query()
            ->available()
            ->forDuration($durationKey)
            ->latest()
            ->first();

        if (! $batch) {
            return redirect()
                ->route('user.desk', ['duree' => $durationKey])
                ->with('info', 'Plus aucun ticket '.TicketDurations::label($durationKey).' disponible.');
        }

        $sale = DB::transaction(function () use ($batch, $durationKey, $price, $user, $today) {
            $nextPosition = $batch->current_index + 1;

            $ticket = Ticket::query()
                ->where('ticket_batch_id', $batch->id)
                ->where('position', $nextPosition)
                ->firstOrFail();

            $ticket->update([
                'revealed' => true,
                'revealed_at' => now(),
            ]);

            $batch->update([
                'current_index' => $nextPosition,
            ]);

            return TicketSale::create([
                'user_id' => $user->id,
                'ticket_id' => $ticket->id,
                'ticket_batch_id' => $batch->id,
                'duration_key' => $durationKey,
                'price' => $price,
                'sold_on' => $today,
                'sold_at' => now(),
            ]);
        });

        return redirect()
            ->route('user.desk', ['duree' => $durationKey])
            ->with(
                'success',
                'Ticket '.TicketDurations::label($durationKey).' vendu · +'.$sale->price.' F'
            );
    }

    /**
     * Clôture la journée : total tickets + montant, puis historique.
     */
    public function closeDay(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $today = now()->toDateString();

        if (DayClosing::query()->where('user_id', $user->id)->whereDate('business_date', $today)->exists()) {
            return redirect()
                ->route('user.day', ['date' => $today])
                ->with('info', 'Cette journée est déjà clôturée.');
        }

        $sales = TicketSale::query()
            ->where('user_id', $user->id)
            ->whereDate('sold_on', $today)
            ->whereNull('day_closing_id')
            ->get();

        if ($sales->isEmpty()) {
            return redirect()
                ->route('user.daily')
                ->with('info', 'Aucune vente aujourd’hui à clôturer.');
        }

        $closing = DB::transaction(function () use ($user, $today, $sales) {
            $breakdown = [];
            foreach (TicketDurations::keys() as $key) {
                $group = $sales->where('duration_key', $key);
                $count = $group->count();
                if ($count === 0) {
                    continue;
                }
                $breakdown[$key] = [
                    'label' => TicketDurations::label($key),
                    'count' => $count,
                    'unit_price' => TicketDurations::price($key),
                    'amount' => (int) $group->sum('price'),
                ];
            }

            $closing = DayClosing::create([
                'user_id' => $user->id,
                'business_date' => $today,
                'tickets_count' => $sales->count(),
                'total_amount' => (int) $sales->sum('price'),
                'breakdown' => $breakdown,
                'closed_at' => now(),
            ]);

            TicketSale::query()
                ->whereIn('id', $sales->pluck('id'))
                ->update(['day_closing_id' => $closing->id]);

            return $closing;
        });

        return redirect()
            ->route('user.day', ['date' => $closing->business_date->format('Y-m-d')])
            ->with('success', 'Journée clôturée : '.$closing->tickets_count.' ticket(s) · '.$closing->total_amount.' F');
    }

    /**
     * Détail / historique d'une journée clôturée.
     */
    public function dayReport(string $date): View|RedirectResponse
    {
        $user = Auth::user();

        $closing = DayClosing::query()
            ->where('user_id', $user->id)
            ->whereDate('business_date', $date)
            ->first();

        if (! $closing) {
            return redirect()
                ->route('user.daily')
                ->with('info', 'Aucune clôture trouvée pour cette date.');
        }

        $sales = TicketSale::query()
            ->where('day_closing_id', $closing->id)
            ->orderBy('sold_at')
            ->get();

        $closings = DayClosing::query()
            ->where('user_id', $user->id)
            ->latest('business_date')
            ->limit(30)
            ->get();

        return view('user.day', [
            'closing' => $closing,
            'sales' => $sales,
            'closings' => $closings,
        ]);
    }
}
