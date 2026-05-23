<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\GeniusPayService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(private GeniusPayService $geniusPay) {}

    public function initialize(Request $request)
    {
        return $this->fail('Utilisez POST /api/v1/checkout pour initier un paiement', 410);
    }

    public function status(Request $request, string $ref)
    {
        $order = Order::where('payment_reference', $ref)->first();

        if (!$order) {
            return $this->fail('Référence de paiement introuvable', 404);
        }

        return $this->ok([
            'status'  => $order->payment_status,
            'orderId' => $order->id,
        ]);
    }

    public function webhook(Request $request)
    {
        if (!$this->geniusPay->verifyWebhookSignature($request)) {
            return response()->json(['error' => 'Signature invalide'], 401);
        }

        $event   = $request->header('X-Webhook-Event');
        $orderId = $request->input('metadata.order_id');
        $order   = $orderId ? Order::find($orderId) : null;

        if ($order) {
            if ($event === 'payment.success') {
                $order->update(['payment_status' => 'paid', 'status' => 'confirmed']);
            } elseif (in_array($event, ['payment.failed', 'payment.cancelled', 'payment.expired'])) {
                $order->update(['payment_status' => 'failed']);
            }
        }

        return response('OK', 200);
    }
}
