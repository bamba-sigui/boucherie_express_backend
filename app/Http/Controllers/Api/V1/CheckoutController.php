<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Services\CheckoutService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    use ApiResponse;

    public function __construct(private CheckoutService $checkout) {}

    public function create(Request $request)
    {
        $validated = $request->validate([
            'items'                  => 'required|array|min:1',
            'items.*.product_id'     => 'required|exists:products,id',
            'items.*.quantity'       => 'required|integer|min:1',
            'items.*.option'         => 'nullable|string',
            'payment_method'         => 'required|in:orange_money,mtn_momo,wave,moov,cash',
            'address_id'             => 'required|integer|exists:addresses,id',
        ]);

        try {
            $order = $this->checkout->createOrder(
                $request->user(),
                $validated['items'],
                $validated['payment_method'],
                $validated['address_id'],
            );

            return $this->ok(new OrderResource($order), 201);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }
}
