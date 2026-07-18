<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;

class AccountController extends Controller
{
    public function index()
    {
        $customer = auth('customer')->user();
        $recentOrders = $customer->orders()->latest()->limit(5)->get();

        return view('shop.account.index', compact('customer', 'recentOrders'));
    }

    public function orders()
    {
        $orders = auth('customer')->user()->orders()->latest()->paginate(10);

        return view('shop.account.orders.index', compact('orders'));
    }

    public function showOrder(Order $order)
    {
        abort_unless($order->customer_id === auth('customer')->id(), 404);

        $order->load('lines.product', 'payments');

        return view('shop.account.orders.show', compact('order'));
    }
}
