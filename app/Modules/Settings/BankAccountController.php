<?php

namespace App\Modules\Settings;

use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BankAccountController extends Controller
{
    public function index()
    {
        return response()->json(BankAccount::orderByDesc('is_default')->orderBy('id')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'account_name'   => 'required|string|max:100',
            'bank_name'      => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'is_default'     => 'boolean',
        ]);

        if (!empty($data['is_default'])) {
            BankAccount::where('is_default', true)->update(['is_default' => false]);
        }

        return response()->json(BankAccount::create($data), 201);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'account_name'   => 'required|string|max:100',
            'bank_name'      => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'is_default'     => 'boolean',
        ]);

        $account = BankAccount::findOrFail($id);

        if (!empty($data['is_default'])) {
            BankAccount::where('is_default', true)->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $account->update($data);
        return response()->json($account);
    }

    public function destroy(int $id)
    {
        BankAccount::findOrFail($id)->delete();
        return response()->json(['message' => 'Rekening dihapus.']);
    }
}
