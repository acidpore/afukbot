<?php

namespace App\Modules\Auth;

use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'endpoint'   => 'required|string',
            'public_key' => 'required|string',
            'auth_token' => 'required|string',
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id'    => Auth::id(),
                'public_key' => $data['public_key'],
                'auth_token' => $data['auth_token'],
            ]
        );

        return response()->json(['message' => 'Subscription disimpan.']);
    }

    public function destroy(Request $request)
    {
        $request->validate(['endpoint' => 'required|string']);
        PushSubscription::where('endpoint', $request->endpoint)
            ->where('user_id', Auth::id())
            ->delete();

        return response()->json(['message' => 'Subscription dihapus.']);
    }

    public function vapidPublicKey()
    {
        return response()->json(['public_key' => config('services.vapid.public_key')]);
    }
}
