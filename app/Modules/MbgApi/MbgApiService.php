<?php

namespace App\Modules\MbgApi;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class MbgApiService
{
    private string $baseUrl;
    private string $token;

    public function __construct()
    {
        $url = config('services.mbg.url') ?? env('MBG_API_URL', '');
        $this->baseUrl = rtrim($url, '/');
        $this->token   = config('services.mbg.token') ?? env('MBG_SERVICE_TOKEN', '');
    }

    private function get(string $path, array $query = []): array
    {
        $response = Http::withHeaders([
            'X-Service-Token' => $this->token,
            'Accept'          => 'application/json',
        ])->get($this->baseUrl . $path, $query);

        $response->throw();

        return $response->json();
    }

    public function getDashboardOverview(array $query = []): array
    {
        return $this->get('/dashboard/overview', $query);
    }

    public function getUsers(array $query = []): array
    {
        return $this->get('/users', $query);
    }

    public function getOrganizations(array $query = []): array
    {
        return $this->get('/organizations', $query);
    }

    public function getSubscriptions(array $query = []): array
    {
        return $this->get('/subscriptions', $query);
    }

    public function getPlans(array $query = []): array
    {
        return $this->get('/plans', $query);
    }

    public function getRoles(array $query = []): array
    {
        return $this->get('/roles', $query);
    }

    public function getVendors(array $query = []): array
    {
        return $this->get('/vendors', $query);
    }

    public function getSales(array $query = []): array
    {
        return $this->get('/sales', $query);
    }

    public function getFoundations(array $query = []): array
    {
        return $this->get('/foundations', $query);
    }

    public function getAuditLogs(array $query = []): array
    {
        return $this->get('/audit-logs', $query);
    }

    public function getSystemSettings(array $query = []): array
    {
        return $this->get('/system-settings', $query);
    }

    public function getNotifications(array $query = []): array
    {
        return $this->get('/notifications', $query);
    }

    public function getKitchenEquipment(array $query = []): array
    {
        return $this->get('/kitchen-equipment', $query);
    }

    public function getMarketplaceSettings(array $query = []): array
    {
        return $this->get('/marketplace-settings', $query);
    }

    public function getSalesPayrolls(array $query = []): array
    {
        return $this->get('/sales-payrolls', $query);
    }
}
