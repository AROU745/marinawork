<?php

namespace App\Http\Controllers;

use App\Models\DayClosing;
use App\Models\TicketSale;
use App\Models\User;
use App\Support\TicketDurations;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminSalesController extends Controller
{
    /**
     * Tableau de bord admin : tickets par user, prix journalier, meilleur jour.
     */
    public function index(Request $request): View
    {
        $date = $request->query('date');
        $selectedDate = $date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            ? $date
            : now()->toDateString();

        $daySales = TicketSale::query()
            ->with('user')
            ->whereDate('sold_on', $selectedDate)
            ->orderBy('sold_at')
            ->get();

        $dayTotalCount = $daySales->count();
        $dayTotalAmount = (int) $daySales->sum('price');

        // Tickets utilisés + prix par vendeur (jour choisi)
        $byUser = $daySales
            ->groupBy('user_id')
            ->map(function ($sales) {
                $user = $sales->first()->user;
                $byDuration = [];
                foreach (TicketDurations::keys() as $key) {
                    $group = $sales->where('duration_key', $key);
                    if ($group->isEmpty()) {
                        continue;
                    }
                    $byDuration[$key] = [
                        'label' => TicketDurations::label($key),
                        'count' => $group->count(),
                        'amount' => (int) $group->sum('price'),
                    ];
                }

                return [
                    'user' => $user,
                    'tickets_count' => $sales->count(),
                    'total_amount' => (int) $sales->sum('price'),
                    'by_duration' => $byDuration,
                    'closed' => DayClosing::query()
                        ->where('user_id', $sales->first()->user_id)
                        ->whereDate('business_date', $sales->first()->sold_on)
                        ->exists(),
                ];
            })
            ->sortByDesc('tickets_count')
            ->values();

        $topSellerToday = $byUser->first();

        // Historique journalier
        $dailyHistory = TicketSale::query()
            ->select([
                'sold_on',
                DB::raw('COUNT(*) as tickets_count'),
                DB::raw('SUM(price) as total_amount'),
                DB::raw('COUNT(DISTINCT user_id) as sellers_count'),
            ])
            ->groupBy('sold_on')
            ->orderByDesc('sold_on')
            ->limit(90)
            ->get()
            ->map(function ($row) {
                if (! $row->sold_on instanceof Carbon) {
                    $row->sold_on = Carbon::parse($row->sold_on);
                }
                $row->tickets_count = (int) $row->tickets_count;
                $row->total_amount = (int) $row->total_amount;
                $row->sellers_count = (int) $row->sellers_count;

                return $row;
            });

        // Jour avec le plus de tickets vendus
        $bestDayByTickets = $dailyHistory->sortByDesc('tickets_count')->first();
        // Jour avec la plus grosse recette
        $bestDayByAmount = $dailyHistory->sortByDesc('total_amount')->first();

        // Cumul global
        $globalTickets = (int) TicketSale::query()->count();
        $globalAmount = (int) TicketSale::query()->sum('price');
        $globalDays = (int) TicketSale::query()->selectRaw('COUNT(DISTINCT sold_on) as c')->value('c');
        $globalSellers = (int) TicketSale::query()->selectRaw('COUNT(DISTINCT user_id) as c')->value('c');

        // Historique général : toutes les dates agrégées par durée
        $allSales = TicketSale::query()->get(['duration_key', 'price', 'user_id']);
        $generalByDuration = collect();
        foreach (TicketDurations::keys() as $key) {
            $group = $allSales->where('duration_key', $key);
            if ($group->isEmpty()) {
                continue;
            }
            $generalByDuration->push([
                'key' => $key,
                'label' => TicketDurations::label($key),
                'unit_price' => TicketDurations::price($key),
                'count' => $group->count(),
                'amount' => (int) $group->sum('price'),
            ]);
        }

        // Cumul par vendeur (toutes dates) + détail durées
        $sellerTotals = TicketSale::query()
            ->select([
                'user_id',
                DB::raw('COUNT(*) as tickets_count'),
                DB::raw('SUM(price) as total_amount'),
                DB::raw('COUNT(DISTINCT sold_on) as days_count'),
                DB::raw('MAX(sold_on) as last_sale_on'),
            ])
            ->groupBy('user_id')
            ->orderByDesc('tickets_count')
            ->get()
            ->map(function ($row) use ($allSales) {
                $row->user = User::query()->find($row->user_id);
                $row->tickets_count = (int) $row->tickets_count;
                $row->total_amount = (int) $row->total_amount;
                $row->days_count = (int) $row->days_count;
                $row->last_sale_on = $row->last_sale_on
                    ? Carbon::parse($row->last_sale_on)
                    : null;

                $userSales = $allSales->where('user_id', $row->user_id);
                $byDuration = [];
                foreach (TicketDurations::keys() as $key) {
                    $group = $userSales->where('duration_key', $key);
                    if ($group->isEmpty()) {
                        continue;
                    }
                    $byDuration[$key] = [
                        'label' => TicketDurations::label($key),
                        'count' => $group->count(),
                        'amount' => (int) $group->sum('price'),
                    ];
                }
                $row->by_duration = $byDuration;

                return $row;
            });

        $topSellerAllTime = $sellerTotals->first();

        $closings = DayClosing::query()
            ->with('user')
            ->latest('business_date')
            ->limit(40)
            ->get();

        return view('admin.sales', [
            'selectedDate' => $selectedDate,
            'selectedDateLabel' => Carbon::parse($selectedDate)->format('d/m/Y'),
            'daySales' => $daySales,
            'dayTotalCount' => $dayTotalCount,
            'dayTotalAmount' => $dayTotalAmount,
            'byUser' => $byUser,
            'topSellerToday' => $topSellerToday,
            'dailyHistory' => $dailyHistory,
            'bestDayByTickets' => $bestDayByTickets,
            'bestDayByAmount' => $bestDayByAmount,
            'globalTickets' => $globalTickets,
            'globalAmount' => $globalAmount,
            'globalDays' => $globalDays,
            'globalSellers' => $globalSellers,
            'generalByDuration' => $generalByDuration,
            'sellerTotals' => $sellerTotals,
            'topSellerAllTime' => $topSellerAllTime,
            'closings' => $closings,
        ]);
    }
}
