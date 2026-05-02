<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderTracking;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Order::with(['user', 'tracking', 'items']);

        if (!$request->user()->hasRole('admin')) {
            $query->where('user_id', $request->user()->id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->latest('created_at')->paginate($request->per_page ?? 20);

        return $this->ok(OrderResource::collection($orders)->response()->getData(true));
    }

    public function show(Request $request, int $id)
    {
        $query = Order::with(['user', 'tracking', 'items']);

        if (!$request->user()->hasRole('admin')) {
            $query->where('user_id', $request->user()->id);
        }

        $order = $query->findOrFail($id);
        return $this->ok(new OrderResource($order));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'items'            => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'shipping_address' => 'required|string',
        ]);

        $total = 0;
        foreach ($validated['items'] as $item) {
            $product = \App\Models\Product::find($item['product_id']);
            $total += $product->price * $item['quantity'];
        }

        $order = Order::create([
            'user_id'          => $request->user()->id,
            'status'           => 'pending',
            'total'            => $total,
            'items'            => $validated['items'],
            'shipping_address' => $validated['shipping_address'],
        ]);

        OrderTracking::create([
            'order_id' => $order->id,
            'status'   => 'pending',
            'note'     => 'Commande créée',
        ]);

        return $this->ok(new OrderResource($order->load('tracking')), 201);
    }

    public function updateStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,paid,preparing,shipping,delivered,cancelled',
            'note'   => 'nullable|string',
        ]);

        $order = Order::findOrFail($id);
        $order->update(['status' => $validated['status']]);

        OrderTracking::create([
            'order_id' => $order->id,
            'status'   => $validated['status'],
            'note'     => $validated['note'] ?? null,
        ]);

        return $this->ok(new OrderResource($order->load('tracking')));
    }

    public function update(Request $request, int $id)
    {
        $order = Order::findOrFail($id);
        $validated = $request->validate([
            'status'           => 'sometimes|in:pending,paid,preparing,shipping,delivered,cancelled',
            'shipping_address' => 'sometimes|string',
        ]);
        $order->update($validated);
        return $this->ok(new OrderResource($order->load('tracking')));
    }

    public function destroy(int $id)
    {
        $order = Order::findOrFail($id);
        $order->delete();
        return $this->ok(['message' => 'Commande supprimée']);
    }

    public function courierLocation(Request $request, int $id)
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);

        if (!$order->courier_id || $order->status !== 'delivering') {
            return $this->fail('Pas de livreur en cours', 404);
        }

        $courier = $order->courier;
        return $this->ok([
            'lat'        => (float) $courier->current_lat,
            'lng'        => (float) $courier->current_lng,
            'heading'    => (float) $courier->current_heading,
            'lastUpdate' => $courier->location_updated_at?->toIso8601String(),
        ]);
    }
}
