<?php

namespace App\Modules\ManualPiutang;

use App\Http\Controllers\Controller;
use App\Models\ManualPiutang;
use Illuminate\Http\Request;

class ManualPiutangController extends Controller
{
    public function index()
    {
        return response()->json(['success' => true, 'data' => ManualPiutang::orderByDesc('id')->get()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'   => 'required|string',
            'amount' => 'required|integer|min:1',
            'notes'  => 'nullable|string',
        ]);

        $item = ManualPiutang::create($request->only('name', 'amount', 'notes'));
        return response()->json(['success' => true, 'data' => $item], 201);
    }

    public function destroy($id)
    {
        ManualPiutang::findOrFail($id)->delete();
        return response()->json(['success' => true, 'data' => null]);
    }
}
