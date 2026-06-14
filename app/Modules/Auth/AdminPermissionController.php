<?php

namespace App\Modules\Auth;

use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AdminPermissionController extends Controller
{
    public const FEATURES = [
        'inventory', 'sales', 'expenses', 'incomes',
        'rab', 'employees', 'attendance', 'payroll',
        'mbg', 'surat_jalan',
    ];

    public function index(int $userId)
    {
        $user = User::where('role', 'admin')->findOrFail($userId);
        $saved = $user->permissions->keyBy('feature');

        $permissions = collect(self::FEATURES)->map(function ($feature) use ($saved) {
            if ($saved->has($feature)) {
                return $saved->get($feature);
            }
            return [
                'feature'    => $feature,
                'can_view'   => true,
                'can_create' => false,
                'can_edit'   => false,
                'can_delete' => false,
                'can_adjust' => false,
            ];
        });

        return response()->json(['data' => $permissions]);
    }

    public function update(Request $request, int $userId)
    {
        $user = User::where('role', 'admin')->findOrFail($userId);

        $data = $request->validate([
            'permissions'              => 'required|array',
            'permissions.*.feature'    => 'required|string|in:' . implode(',', self::FEATURES),
            'permissions.*.can_view'   => 'boolean',
            'permissions.*.can_create' => 'boolean',
            'permissions.*.can_edit'   => 'boolean',
            'permissions.*.can_delete' => 'boolean',
            'permissions.*.can_adjust' => 'boolean',
        ]);

        foreach ($data['permissions'] as $perm) {
            UserPermission::updateOrCreate(
                ['user_id' => $user->id, 'feature' => $perm['feature']],
                [
                    'can_view'   => $perm['can_view']   ?? true,
                    'can_create' => $perm['can_create'] ?? false,
                    'can_edit'   => $perm['can_edit']   ?? false,
                    'can_delete' => $perm['can_delete'] ?? false,
                    'can_adjust' => $perm['can_adjust'] ?? false,
                ]
            );
        }

        return response()->json(['message' => 'Hak akses disimpan.']);
    }
}
