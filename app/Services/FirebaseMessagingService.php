<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FirebaseMessagingService
{
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        try {
            $messaging = app('firebase.messaging');
            $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $token)
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body))
                ->withData(array_map('strval', $data));

            $messaging->send($message);
            return true;
        } catch (\Throwable $e) {
            Log::error('FCM send failed', ['error' => $e->getMessage(), 'token' => substr($token, 0, 20)]);
            return false;
        }
    }

    public function sendToTopic(string $topic, string $title, string $body, array $data = []): bool
    {
        try {
            $messaging = app('firebase.messaging');
            $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('topic', $topic)
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body))
                ->withData(array_map('strval', $data));

            $messaging->send($message);
            return true;
        } catch (\Throwable $e) {
            Log::error('FCM topic send failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function sendToMany(array $tokens, string $title, string $body, array $data = []): array
    {
        try {
            $messaging = app('firebase.messaging');
            $message = \Kreait\Firebase\Messaging\CloudMessage::new()
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body))
                ->withData(array_map('strval', $data));

            $report = $messaging->sendMulticast($message, $tokens);
            return [
                'success' => $report->successes()->count(),
                'failure' => $report->failures()->count(),
            ];
        } catch (\Throwable $e) {
            Log::error('FCM multicast failed', ['error' => $e->getMessage()]);
            return ['success' => 0, 'failure' => count($tokens)];
        }
    }
}
