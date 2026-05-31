<?php

namespace App\Modules\MbgApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

class MbgApiController
{
    public function __construct(private MbgApiService $service) {}

    private function proxy(callable $call): JsonResponse
    {
        try {
            $data = $call();
            return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
        } catch (ConnectionException $e) {
            Log::error('MBG ConnectionException: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Tidak dapat terhubung ke server MBG: ' . $e->getMessage()], 502);
        } catch (RequestException $e) {
            Log::error('MBG RequestException: ' . $e->getMessage());
            $body = $e->response->json() ?? [];
            $msg  = $body['meta']['message'] ?? $e->getMessage();
            return response()->json(['success' => false, 'message' => $msg], $e->response->status());
        } catch (\Throwable $e) {
            Log::error('MBG Throwable: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function dashboardOverview(Request $request): JsonResponse
    {
        return $this->proxy(fn() => $this->service->getDashboardOverview($request->query()));
    }

    public function users(Request $request): JsonResponse
    {
        return $this->proxy(fn() => $this->service->getUsers($request->query()));
    }

    public function organizations(Request $request): JsonResponse
    {
        return $this->proxy(fn() => $this->service->getOrganizations($request->query()));
    }

    public function subscriptions(Request $request): JsonResponse
    {
        return $this->proxy(fn() => $this->service->getSubscriptions($request->query()));
    }

    public function plans(Request $request): JsonResponse
    {
        return $this->proxy(fn() => $this->service->getPlans($request->query()));
    }

    public function roles(Request $request): JsonResponse
    {
        return $this->proxy(fn() => $this->service->getRoles($request->query()));
    }

    public function vendors(Request $request): JsonResponse
    {
        return $this->proxy(fn() => $this->service->getVendors($request->query()));
    }

    public function sales(Request $request): JsonResponse
    {
        return $this->proxy(fn() => $this->service->getSales($request->query()));
    }

    public function foundations(Request $request): JsonResponse
    {
        return $this->proxy(fn() => $this->service->getFoundations($request->query()));
    }

    public function auditLogs(Request $request): JsonResponse
    {
        return $this->proxy(fn() => $this->service->getAuditLogs($request->query()));
    }

    public function systemSettings(Request $request): JsonResponse
    {
        return $this->proxy(fn() => $this->service->getSystemSettings($request->query()));
    }

    public function notifications(Request $request): JsonResponse
    {
        return $this->proxy(fn() => $this->service->getNotifications($request->query()));
    }

    public function kitchenEquipment(Request $request): JsonResponse
    {
        return $this->proxy(fn() => $this->service->getKitchenEquipment($request->query()));
    }

    public function marketplaceSettings(Request $request): JsonResponse
    {
        return $this->proxy(fn() => $this->service->getMarketplaceSettings($request->query()));
    }

    public function salesPayrolls(Request $request): JsonResponse
    {
        return $this->proxy(fn() => $this->service->getSalesPayrolls($request->query()));
    }
}
