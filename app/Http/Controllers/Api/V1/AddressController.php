<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AddressController extends Controller
{
    public function index(): JsonResponse
    {
        $addresses = Address::where('user_id', Auth::id())->get();
        return response()->json($addresses);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'address' => 'required|string',
            'city' => 'required|string|max:255',
            'postal_code' => 'required|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'is_default' => 'boolean',
        ]);

        if ($validated['is_default'] ?? false) {
            Address::where('user_id', Auth::id())->update(['is_default' => false]);
        }

        $address = Address::create([
            ...$validated,
            'user_id' => Auth::id(),
        ]);

        return response()->json($address, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $address = Address::where('user_id', Auth::id())->findOrFail($id);

        if ($request->is_default) {
            Address::where('user_id', Auth::id())->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $address->update($request->validated());
        return response()->json($address);
    }

    public function destroy(int $id): JsonResponse
    {
        $address = Address::where('user_id', Auth::id())->findOrFail($id);
        $address->delete();
        return response()->json(['message' => 'Address deleted']);
    }
}