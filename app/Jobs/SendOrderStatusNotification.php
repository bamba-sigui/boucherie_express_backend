<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Models\Order;
use App\Services\FirebaseMessagingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOrderStatusNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public Order $order, public string $status) {}

    public function handle(FirebaseMessagingService $fcm): void
    {
        $messages = [
            'confirmed'  => ['Commande confirmée ✅', 'Votre commande a été acceptée'],
            'preparing'  => ['En préparation 🔪', 'Nous préparons votre viande fraîche'],
            'delivering' => ['En route 🛵', 'Votre livreur arrive sous peu'],
            'delivered'  => ['Livrée 🍖', 'Bon appétit ! Notez votre commande'],
            'cancelled'  => ['Annulée', 'Votre commande a été annulée'],
        ];

        if (!isset($messages[$this->status])) return;

        [$title, $body] = $messages[$this->status];
        $token = $this->order->user->fcm_token;
        if (!$token) return;

        $sent = $fcm->sendToToken($token, $title, $body, [
            'type'     => 'order_status',
            'order_id' => (string) $this->order->id,
            'status'   => $this->status,
        ]);

        NotificationLog::create([
            'user_id' => $this->order->user_id,
            'title'   => $title,
            'body'    => $body,
            'type'    => 'order_status',
            'data'    => ['order_id' => $this->order->id, 'status' => $this->status],
            'sent'    => $sent,
            'sent_at' => now(),
        ]);
    }
}
