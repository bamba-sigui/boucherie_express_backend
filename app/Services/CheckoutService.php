<?php

namespace App\Services;

use App\Models\Address;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function createOrder(User $user, array $items, string $paymentMethod, int $addressId): Order
    {
        return DB::transaction(function () use ($user, $items, $paymentMethod, $addressId) {
            $address = Address::where('user_id', $user->id)->findOrFail($addressId);
            $deliveryFee = $this->computeDeliveryFee($address->city, $items);

            $totalPrice = 0;
            $orderItemsData = [];

            foreach ($items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Stock insuffisant pour {$product->name}");
                }

                $product->decrement('stock', $item['quantity']);

                $price = $this->resolvePrice($product, $user);
                $totalPrice += $price * $item['quantity'];

                $orderItemsData[] = [
                    'product_id'   => $product->id,
                    'product_name' => $product->name,
                    'price'        => $price,
                    'quantity'     => $item['quantity'],
                    'option'       => $item['option'] ?? null,
                    'image_url'    => is_array($product->images) ? ($product->images[0] ?? $product->image) : $product->image,
                ];
            }

            $order = Order::create([
                'user_id'          => $user->id,
                'total'            => $totalPrice,
                'total_price'      => $totalPrice,
                'delivery_fee'     => $deliveryFee,
                'total_amount'     => $totalPrice + $deliveryFee,
                'delivery_address' => "{$address->address}, {$address->city}",
                'shipping_address' => "{$address->address}, {$address->city}",
                'address_id'       => $address->id,
                'payment_method'   => $paymentMethod,
                'payment_status'   => 'pending',
                'status'           => 'pending',
                'ordered_at'       => now(),
            ]);

            $order->items()->createMany($orderItemsData);

            OrderStatusHistory::create([
                'order_id'     => $order->id,
                'status'       => 'pending',
                'completed_at' => now(),
            ]);

            return $order->load(['items', 'statusHistories']);
        });
    }

    private function resolvePrice(Product $product, User $user): int
    {
        // Premium : 5% de réduction
        if ($user->is_premium ?? false) {
            return (int) round($product->price * 0.95);
        }

        return (int) $product->price;
    }

    private function computeDeliveryFee(string $city, array $items): int
    {
        $zone = DeliveryZone::where('city', $city)->where('is_active', true)->first();
        if (!$zone) return 1500;

        $totalAmount = collect($items)->sum(function ($i) {
            $product = Product::find($i['product_id']);
            return $product ? $product->price * $i['quantity'] : 0;
        });

        if ($totalAmount >= $zone->free_delivery_threshold) return 0;

        return $zone->fee;
    }
}
