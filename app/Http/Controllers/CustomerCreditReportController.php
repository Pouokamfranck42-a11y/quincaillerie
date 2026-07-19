<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Support\Carbon;

/**
 * Vue d'ensemble des encours clients (Phase 2 — comptes professionnels) : jusqu'ici,
 * "qui me doit de l'argent et depuis quand" n'était visible qu'en ouvrant chaque fiche
 * client une par une (customers.statement). Ce rapport liste tous les clients avec un
 * encours, triés par ancienneté de retard puis montant — vue de relance/recouvrement.
 */
class CustomerCreditReportController extends Controller
{
    public function index()
    {
        $rows = Customer::query()
            ->withSum(['sales as due_total' => fn ($q) => $q->where('payment_status', 'due')->where('status', '!=', 'cancelled')], 'total')
            ->withSum(['sales as due_paid' => fn ($q) => $q->where('payment_status', 'due')->where('status', '!=', 'cancelled')], 'paid_amount')
            ->withMin(['sales as oldest_due_date' => fn ($q) => $q->where('payment_status', 'due')->where('status', '!=', 'cancelled')], 'due_date')
            ->get()
            ->map(function (Customer $customer) {
                $outstanding = round((float) ($customer->due_total ?? 0) - (float) ($customer->due_paid ?? 0), 2);
                $oldestDueDate = $customer->oldest_due_date ? Carbon::parse($customer->oldest_due_date) : null;
                $isOverdue = $oldestDueDate && $oldestDueDate->isPast();

                return [
                    'customer' => $customer,
                    'outstanding' => $outstanding,
                    'oldest_due_date' => $oldestDueDate,
                    'days_overdue' => $isOverdue ? $oldestDueDate->diffInDays(now()) : 0,
                    'is_overdue' => $isOverdue,
                ];
            })
            ->filter(fn (array $row) => $row['outstanding'] > 0.01)
            ->sortByDesc(fn (array $row) => $row['days_overdue'] * 100000000 + $row['outstanding'])
            ->values();

        return view('reports.customer-credit', [
            'rows' => $rows,
            'totalOutstanding' => $rows->sum('outstanding'),
            'totalOverdue' => $rows->where('is_overdue', true)->sum('outstanding'),
            'overdueCount' => $rows->where('is_overdue', true)->count(),
        ]);
    }
}
