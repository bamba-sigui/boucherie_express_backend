<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CheckoutService;
use App\Services\GeniusPayService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    use ApiResponse;

    public function __construct(
        private CheckoutService $checkout,
        private GeniusPayService $geniusPay,
    ) {}

    public function create(Request $request)
    {
        $validated = $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.option'     => 'nullable|string',
            'payment_method'     => 'required|in:orange_money,mtn_momo,wave,cash',
            'address_id'         => 'required|integer|exists:addresses,id',
            'note'               => 'nullable|string|max:500',
            'phone'              => 'nullable|string|max:30',
        ]);

        try {
            $order = $this->checkout->createOrder(
                $request->user(),
                $validated['items'],
                $validated['payment_method'],
                $validated['address_id'],
            );

            if ($validated['note'] ?? null) {
                $order->update(['note' => $validated['note']]);
            }

            if ($validated['payment_method'] === 'cash') {
                return $this->ok([
                    'orderId'     => $order->id,
                    'checkoutUrl' => null,
                    'paymentRef'  => null,
                ], 201);
            }

            $phone       = $validated['phone'] ?? $request->user()->phone ?? '';
            $callbackUrl = url('/api/v1/webhooks/genius-pay');

            $result = $this->geniusPay->initializePayment(
                $order->total_amount,
                (string) $order->id,
                $phone,
                $callbackUrl,
            );

            $ref = $result['reference'] ?? null;
            $order->update(['payment_reference' => $ref]);

            return $this->ok([
                'orderId'     => $order->id,
                'checkoutUrl' => $result['checkout_url'] ?? null,
                'paymentRef'  => $ref,
            ], 201);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 422);
        }
    }
}
