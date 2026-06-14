<?php

namespace App\Modules\Auth;

use App\Models\AdminNotification;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = AdminNotification::orderByDesc('created_at')->limit(30)->get();
        $unreadCount   = AdminNotification::unread()->count();

        return response()->json([
            'data'         => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function markRead(int $id)
    {
        AdminNotification::findOrFail($id)->update(['read_at' => now()]);
        return response()->json(['unread_count' => AdminNotification::unread()->count()]);
    }

    public function markAllRead()
    {
        AdminNotification::unread()->update(['read_at' => now()]);
        return response()->json(['unread_count' => 0]);
    }
}
