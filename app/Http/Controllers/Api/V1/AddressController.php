<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    use ApiResponse;

    private function listResponse(int $userId)
    {
        return $this->ok(
            AddressResource::collection(
                Address::where('user_id', $userId)->orderByDesc('is_default')->get()
            )
        );
    }

    public function index(Request $request)
    {
        return $this->listResponse($request->user()->id);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label'       => 'required|string|max:255',
            'address'     => 'required|string',
            'city'        => 'required|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
            'is_default'  => 'boolean',
        ]);

        if ($validated['is_default'] ?? false) {
            Address::where('user_id', $request->user()->id)->update(['is_default' => false]);
        }

        Address::create(array_merge($validated, ['user_id' => $request->user()->id]));

        return $this->listResponse($request->user()->id);
    }

    public function update(Request $request, int $id)
    {
        $address = Address::where('user_id', $request->user()->id)->findOrFail($id);
        $validated = $request->validate([
            'label'       => 'sometimes|string|max:255',
            'address'     => 'sometimes|string',
            'city'        => 'sometimes|string|max:255',
            'postal_code' => 'sometimes|string|max:20',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
            'is_default'  => 'sometimes|boolean',
        ]);

        if ($validated['is_default'] ?? false) {
            Address::where('user_id', $request->user()->id)->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $address->update($validated);

        return $this->listResponse($request->user()->id);
    }

    public function destroy(Request $request, int $id)
    {
        Address::where('user_id', $request->user()->id)->findOrFail($id)->delete();
        return $this->listResponse($request->user()->id);
    }

    public function setDefault(Request $request, int $id)
    {
        $userId = $request->user()->id;
        DB::transaction(function () use ($userId, $id) {
            Address::where('user_id', $userId)->update(['is_default' => false]);
            Address::where('user_id', $userId)->where('id', $id)->update(['is_default' => true]);
        });
        return $this->listResponse($userId);
    }
}
