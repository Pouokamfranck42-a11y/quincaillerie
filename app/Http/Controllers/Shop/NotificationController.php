<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = auth('customer')->user()->notifications()->paginate(20);

        return view('shop.notifications.index', compact('notifications'));
    }

    public function markRead(Request $request, string $notification)
    {
        auth('customer')->user()->notifications()->where('id', $notification)->first()?->markAsRead();

        return back();
    }

    public function markAllRead(Request $request)
    {
        auth('customer')->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Notifications marquées comme lues.');
    }
}
