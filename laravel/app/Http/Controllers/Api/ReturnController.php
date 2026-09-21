<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReturnModel;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    public function index()
    {
        return response()->json(ReturnModel::all());
    }

    public function show($id)
    {
        $return = ReturnModel::findOrFail($id);
        return response()->json($return);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_reference' => 'required|string',
            'product_name' => 'required|string',
            'reason' => 'required|string',
            'request_date' => 'required|date',
            'status' => 'in:pending,accepted,rejected',
        ]);

        $return = ReturnModel::create($validated);

        return response()->json($return, 201);
    }

    public function update(Request $request, $id)
    {
        $return = ReturnModel::findOrFail($id);

        $validated = $request->validate([
            'order_reference' => 'sometimes|required|string',
            'product_name' => 'sometimes|required|string',
            'reason' => 'sometimes|required|string',
            'request_date' => 'sometimes|required|date',
            'status' => 'sometimes|in:pending,accepted,rejected',
        ]);

        $return->update($validated);

        return response()->json($return);
    }

    public function destroy($id)
    {
        $return = ReturnModel::findOrFail($id);
        $return->delete();

        return response()->json(null, 204);
    }
}
