<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDeliveryController extends Controller
{
    /**
     * Admin : tous les clients + toutes les livraisons (en attente / effectuées).
     */
    public function index(Request $request): View
    {
        $tab = $request->query('onglet', 'livraisons');
        if (! in_array($tab, ['livraisons', 'clients'], true)) {
            $tab = 'livraisons';
        }

        $status = $request->query('statut', 'tous');
        if (! in_array($status, ['tous', 'en_attente', 'livree'], true)) {
            $status = 'tous';
        }

        $deliveriesQuery = Delivery::query()
            ->with(['user', 'customer'])
            ->latest('delivery_date')
            ->latest('id');

        if ($status !== 'tous') {
            $deliveriesQuery->where('status', $status);
        }

        $deliveries = $deliveriesQuery->limit(300)->get();

        $pendingCount = (int) Delivery::query()->where('status', Delivery::STATUS_PENDING)->count();
        $doneCount = (int) Delivery::query()->where('status', Delivery::STATUS_DONE)->count();
        $customersCount = (int) Customer::query()->count();

        $customers = Customer::query()
            ->with('user')
            ->withCount('deliveries')
            ->latest()
            ->limit(300)
            ->get();

        return view('admin.deliveries', [
            'tab' => $tab,
            'status' => $status,
            'deliveries' => $deliveries,
            'customers' => $customers,
            'pendingCount' => $pendingCount,
            'doneCount' => $doneCount,
            'customersCount' => $customersCount,
            'totalDeliveries' => $pendingCount + $doneCount,
        ]);
    }
}
