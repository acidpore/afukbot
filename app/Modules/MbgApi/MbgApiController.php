<?php

namespace App\Modules\MbgApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MbgApiController
{
    public function __construct(private MbgApiService $service) {}

    public function dashboardOverview(Request $request): JsonResponse
    {
        $data = $this->service->getDashboardOverview($request->query());
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
    }

    public function users(Request $request): JsonResponse
    {
        $data = $this->service->getUsers($request->query());
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
    }

    public function organizations(Request $request): JsonResponse
    {
        $data = $this->service->getOrganizations($request->query());
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
    }

    public function subscriptions(Request $request): JsonResponse
    {
        $data = $this->service->getSubscriptions($request->query());
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
    }

    public function plans(Request $request): JsonResponse
    {
        $data = $this->service->getPlans($request->query());
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
    }

    public function roles(Request $request): JsonResponse
    {
        $data = $this->service->getRoles($request->query());
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
    }

    public function vendors(Request $request): JsonResponse
    {
        $data = $this->service->getVendors($request->query());
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
    }

    public function sales(Request $request): JsonResponse
    {
        $data = $this->service->getSales($request->query());
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
    }

    public function foundations(Request $request): JsonResponse
    {
        $data = $this->service->getFoundations($request->query());
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $data = $this->service->getAuditLogs($request->query());
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
    }

    public function systemSettings(Request $request): JsonResponse
    {
        $data = $this->service->getSystemSettings($request->query());
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $data = $this->service->getNotifications($request->query());
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
    }

    public function kitchenEquipment(Request $request): JsonResponse
    {
        $data = $this->service->getKitchenEquipment($request->query());
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
    }

    public function marketplaceSettings(Request $request): JsonResponse
    {
        $data = $this->service->getMarketplaceSettings($request->query());
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
    }

    public function salesPayrolls(Request $request): JsonResponse
    {
        $data = $this->service->getSalesPayrolls($request->query());
        return response()->json(['success' => true, 'message' => 'OK', 'data' => $data]);
    }
}
