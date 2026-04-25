<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderTracking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['user', 'tracking']);

        if (Auth::check() && !$request->user()->is_admin) {
            $query->where('user_id', Auth::id());
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->latest()->paginate($request->per_page ?? 50);
        return response()->json($orders);
    }

    public function show(int $id): JsonResponse
    {
        $order = Order::with(['user', 'tracking'])->findOrFail($id);
        return response()->json($order);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'shipping_address' => 'required|string',
        ]);

        $total = 0;
        foreach ($validated['items'] as $item) {
            $product = \App\Models\Product::find($item['product_id']);
            $total += $product->price * $item['quantity'];
        }

        $order = Order::create([
            'user_id' => Auth::id(),
            'status' => OrderTracking::STATUS_PENDING,
            'total' => $total,
            'items' => $validated['items'],
            'shipping_address' => $validated['shipping_address'],
        ]);

        OrderTracking::create([
            'order_id' => $order->id,
            'status' => OrderTracking::STATUS_PENDING,
            'note' => 'Commande créée',
        ]);

        return response()->json($order->load('tracking'), 201);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,paid,preparing,shipping,delivered,cancelled',
            'note' => 'nullable|string',
        ]);

        $order = Order::findOrFail($id);
        $order->update(['status' => $validated['status']]);

        OrderTracking::create([
            'order_id' => $order->id,
            'status' => $validated['status'],
            'note' => $validated['note'] ?? null,
        ]);

        return response()->json($order->load('tracking'));
    }
}