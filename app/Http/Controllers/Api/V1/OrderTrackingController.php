<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class OrderTrackingController extends Controller
{
    use ApiResponse;

    public function show(Request $request, int $id)
    {
        $order = Order::with('courier')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $history = OrderStatusHistory::where('order_id', $id)->get()->keyBy('status');

        $steps = [
            [
                'step'        => 'Commande reçue',
                'subtitle'    => 'Votre commande a été confirmée',
                'completed'   => $history->has('confirmed') || $history->has('pending'),
                'completedAt' => ($history->get('confirmed') ?? $history->get('pending'))?->completed_at?->toIso8601String(),
            ],
            [
                'step'        => 'En préparation',
                'subtitle'    => 'Nous préparons votre viande',
                'completed'   => $history->has('preparing'),
                'completedAt' => $history->get('preparing')?->completed_at?->toIso8601String(),
            ],
            [
                'step'        => 'En livraison',
                'subtitle'    => 'Votre livreur est en route',
                'completed'   => $history->has('delivering'),
                'completedAt' => $history->get('delivering')?->completed_at?->toIso8601String(),
            ],
            [
                'step'        => 'Livrée',
                'subtitle'    => 'Commande livrée',
                'completed'   => $history->has('delivered'),
                'completedAt' => $history->get('delivered')?->completed_at?->toIso8601String(),
            ],
        ];

        return $this->ok([
            'steps'   => $steps,
            'courier' => $order->courier ? [
                'id'         => $order->courier->id,
                'name'       => $order->courier->name,
                'phone'      => $order->courier->phone,
                'photoUrl'   => $order->courier->photo_url,
                'rating'     => (float) $order->courier->rating,
                'vehicle'    => $order->courier->vehicle,
                'currentLat' => $order->courier->current_lat,
                'currentLng' => $order->courier->current_lng,
            ] : null,
            'eta' => $order->eta?->toIso8601String(),
        ]);
    }
}
