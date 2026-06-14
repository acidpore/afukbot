<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\PushSubscription;
use App\Models\User;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class WebPushService
{
    private WebPush $webPush;

    public function __construct()
    {
        $auth = [
            'VAPID' => [
                'subject'    => config('app.url'),
                'publicKey'  => config('services.vapid.public_key'),
                'privateKey' => config('services.vapid.private_key'),
            ],
        ];

        $this->webPush = new WebPush($auth);
    }

    public function sendToSuperAdmins(string $title, string $body, string $url = '/', string $type = 'info'): void
    {
        // Simpan ke DB untuk in-app notification center
        AdminNotification::create([
            'title' => $title,
            'body'  => $body,
            'url'   => $url,
            'type'  => $type,
        ]);

        // Kirim push ke semua device super admin
        $superAdmins = User::where('role', 'super_admin')->where('status', 'active')->get();

        foreach ($superAdmins as $admin) {
            $subscriptions = PushSubscription::where('user_id', $admin->id)->get();
            foreach ($subscriptions as $sub) {
                $this->send($sub, $title, $body, $url);
            }
        }

        $this->flush();
    }

    public function send(PushSubscription $sub, string $title, string $body, string $url = '/'): void
    {
        $subscription = Subscription::create([
            'endpoint'   => $sub->endpoint,
            'publicKey'  => $sub->public_key,
            'authToken'  => $sub->auth_token,
        ]);

        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'url'   => $url,
        ]);

        $this->webPush->queueNotification($subscription, $payload);
    }

    public function flush(): void
    {
        foreach ($this->webPush->flush() as $report) {
            if (!$report->isSuccess()) {
                $endpoint = $report->getRequest()->getUri()->__toString();
                PushSubscription::where('endpoint', $endpoint)->delete();
            }
        }
    }
}
