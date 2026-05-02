# Boucherie Express — Plan d'implémentation complet (tous sprints)

> Document de référence exhaustif. Couvre la refonte des fondations, les endpoints API manquants, le backoffice Filament détaillé, et tous les volets promis dans le pitch investisseur (B2B, fidélité Premium, géolocalisation temps réel, traçabilité avancée, multi-fournisseurs).
>
> Lecture conjointe avec ton `IMPLEMENT.md` existant : ce document **ne répète pas** ce qui y figure déjà, sauf quand il faut rectifier ou compléter.

---

## Table des matières

1. Décisions architecturales préalables
2. Sprint 1 bis — Refonte des fondations (Firebase + format réponse)
3. Sprint 2 — Endpoints API manquants
4. Sprint 3 — Notifications push & uploads
5. Sprint 4 — Paiements CinetPay
6. Sprint 5 — **Backoffice Filament exhaustif (priorité)**
7. Sprint 6 — Volet B2B (restaurants, maquis, corporate)
8. Sprint 7 — Programme fidélité Premium
9. Sprint 8 — Géolocalisation livreurs temps réel
10. Sprint 9 — Optimisation des tournées de livraison
11. Sprint 10 — Traçabilité avancée (chaîne du froid + lots)
12. Sprint 11 — Multi-fournisseurs (éleveurs / abattoirs)
13. Sprint 12 — Analytics, CRM & marketing
14. Annexes — Sécurité, performance, déploiement, tests

---

## 1. Décisions architecturales préalables

### 1.1 Migration Sanctum → Firebase Auth

Tu as déjà Sanctum en place. Le plan exige Firebase. **Décision recommandée : migrer**, parce que (1) Firebase fournit l'OTP par SMS gratuit attendu sur le marché ivoirien, (2) ça mutualise avec FCM pour les notifs, (3) tu n'as pas encore d'utilisateurs en prod donc le coût est faible.

**Plan de migration** :

1. Garder Sanctum **temporairement** pour l'admin Filament (login web classique). Firebase ne sera utilisé QUE pour l'app Flutter (API mobile).
2. Séparer les deux mondes proprement :
   - Routes `routes/api.php` → middleware `firebase.auth`
   - Routes `routes/web.php` (Filament) → middleware `auth` (Sanctum/session)
3. La table `users` doit gérer les deux : un user admin a un mot de passe + un rôle, un user client a un firebase_uid + pas de mot de passe.

**Modification de la table users** :

```php
Schema::table('users', function (Blueprint $table) {
    $table->string('firebase_uid')->nullable()->unique()->after('id');
    $table->string('photo_url')->nullable();
    $table->string('fcm_token')->nullable();
    $table->enum('account_type', ['b2c', 'b2b', 'admin', 'partner'])->default('b2c');
    $table->boolean('is_premium')->default(false);
    $table->timestamp('premium_until')->nullable();
    // password reste nullable (pas de password pour les clients Firebase)
    $table->string('password')->nullable()->change();
});
```

**Note importante** : on garde l'`id` auto-increment classique pour ne pas casser les relations existantes. Le `firebase_uid` devient une colonne secondaire indexée. Le middleware Firebase trouve l'utilisateur via `firebase_uid`, pas via `id`.

### 1.2 Format de réponse unifié

Toutes les routes `/api/v1/*` doivent renvoyer `{"success": bool, "data": ...}` ou `{"success": false, "error": "..."}`. Ce qui signifie :

- Créer le trait `ApiResponse` (déjà décrit dans ton `IMPLEMENT.md`)
- Adapter **tous les controllers existants** (`ProductController`, `OrderController`, `CategoryController`, `AddressController`) pour utiliser ce format
- Adapter **toutes les `Resource` Laravel** pour sortir en camelCase (`totalPrice`, `photoUrl`, etc.)

C'est rébarbatif mais indispensable. Compte une demi-journée pour passer en revue les controllers existants.

### 1.3 Stack finale recommandée

| Composant | Choix | Raison |
|---|---|---|
| Auth API mobile | Firebase Auth | OTP SMS, intégration FCM |
| Auth admin web | Sanctum + session | Filament natif, simple |
| Cache + Queues | Redis | Performance + jobs async |
| Storage médias | Local en dev, **Cloudflare R2** en prod | Moins cher que S3, CDN inclus |
| PSP | CinetPay | Couvre OM, MTN, Wave, Moov en une seule API |
| Carte / géoloc | Google Maps API ou OpenStreetMap | Maps pour itinéraire, OSM pour économiser |
| Monitoring | Sentry (gratuit jusqu'à 5K events/mois) | Détecter les bugs prod |
| Backups DB | Spatie Laravel Backup | Cron quotidien, push S3/R2 |

---

## 2. Sprint 1 bis — Refonte des fondations ✅ TERMINÉ

### 2.1 Installation Firebase

```bash
composer require kreait/laravel-firebase
php artisan vendor:publish --provider="Kreait\Laravel\Firebase\ServiceProvider"
```

Crée `storage/app/firebase/service-account.json` avec la clé téléchargée depuis Firebase Console.

`.env` :
```env
FIREBASE_CREDENTIALS=storage/app/firebase/service-account.json
FIREBASE_PROJECT_ID=boucherie-express
```

### 2.2 FirebaseAuthService

```php
// app/Services/FirebaseAuthService.php
namespace App\Services;

use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

class FirebaseAuthService
{
    public function verifyToken(string $idToken): ?array
    {
        try {
            $verifiedToken = Firebase::auth()->verifyIdToken($idToken);
            $claims = $verifiedToken->claims();

            return [
                'uid' => $claims->get('sub'),
                'email' => $claims->get('email'),
                'phone' => $claims->get('phone_number'),
                'name' => $claims->get('name'),
                'picture' => $claims->get('picture'),
                'email_verified' => $claims->get('email_verified', false),
            ];
        } catch (FailedToVerifyToken $e) {
            return null;
        }
    }
}
```

### 2.3 FirebaseAuthMiddleware

```php
// app/Http/Middleware/FirebaseAuthMiddleware.php
namespace App\Http\Middleware;

use App\Models\User;
use App\Services\FirebaseAuthService;
use Closure;
use Illuminate\Http\Request;

class FirebaseAuthMiddleware
{
    public function __construct(private FirebaseAuthService $firebase) {}

    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['success' => false, 'error' => 'Token manquant'], 401);
        }

        $payload = $this->firebase->verifyToken($token);

        if (!$payload) {
            return response()->json(['success' => false, 'error' => 'Token invalide ou expiré'], 401);
        }

        $user = User::firstOrCreate(
            ['firebase_uid' => $payload['uid']],
            [
                'email' => $payload['email'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'name' => $payload['name'] ?? 'Utilisateur',
                'photo_url' => $payload['picture'] ?? null,
                'account_type' => 'b2c',
            ]
        );

        $request->setUserResolver(fn() => $user);
        return $next($request);
    }
}
```

Enregistrer dans `bootstrap/app.php` :

```php
$middleware->alias([
    'firebase.auth' => \App\Http\Middleware\FirebaseAuthMiddleware::class,
]);
```

### 2.4 Trait ApiResponse

```php
// app/Traits/ApiResponse.php
namespace App\Traits;

trait ApiResponse
{
    protected function ok($data = null, int $status = 200)
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    protected function fail(string $error, int $status = 400, array $extra = [])
    {
        return response()->json(['success' => false, 'error' => $error] + $extra, $status);
    }
}
```

### 2.5 ProfileController

```php
// app/Http/Controllers/Api/V1/ProfileController.php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    use ApiResponse;

    public function show(Request $request)
    {
        return $this->ok(new UserResource($request->user()->load('addresses')));
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $user->update($request->validated());
        return $this->ok(new UserResource($user->fresh()->load('addresses')));
    }

    public function updateFcmToken(Request $request)
    {
        $request->validate(['fcm_token' => 'required|string']);
        $request->user()->update(['fcm_token' => $request->input('fcm_token')]);
        return $this->ok(['saved' => true]);
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate(['avatar' => 'required|image|mimes:jpeg,jpg,png|max:2048']);

        $user = $request->user();
        $disk = config('app.avatar_disk', 'public');

        if ($user->photo_url && str_contains($user->photo_url, '/storage/avatars/')) {
            $oldPath = str_replace(asset('storage') . '/', '', $user->photo_url);
            Storage::disk($disk)->delete($oldPath);
        }

        $path = $request->file('avatar')->store("avatars/{$user->id}", $disk);
        $url = Storage::disk($disk)->url($path);
        $user->update(['photo_url' => $url]);

        return $this->ok(['photoUrl' => $url]);
    }
}
```

### 2.6 UserResource

```php
// app/Http/Resources/UserResource.php
public function toArray($request): array
{
    return [
        'id' => $this->firebase_uid ?? (string) $this->id,
        'email' => $this->email,
        'name' => $this->name,
        'phone' => $this->phone,
        'photoUrl' => $this->photo_url,
        'accountType' => $this->account_type,
        'isPremium' => (bool) $this->is_premium,
        'premiumUntil' => $this->premium_until?->toIso8601String(),
        'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
        'createdAt' => $this->created_at?->toIso8601String(),
    ];
}
```

### 2.7 AuthController public

```php
public function checkPhone(Request $request)
{
    $request->validate(['phone' => 'required|string']);
    $exists = User::where('phone', $request->query('phone'))
        ->where('account_type', '!=', 'admin')
        ->exists();
    return $this->ok(['exists' => $exists]);
}
```

### 2.8 Routes mises à jour

```php
// routes/api.php
Route::prefix('v1')->group(function () {
    Route::get('auth/check-phone', [AuthController::class, 'checkPhone']);

    Route::middleware('firebase.auth')->group(function () {
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::post('profile/fcm-token', [ProfileController::class, 'updateFcmToken']);
        Route::post('users/me/avatar', [ProfileController::class, 'uploadAvatar']);

        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{id}', [ProductController::class, 'show']);

        Route::get('categories', [CategoryController::class, 'index']);

        Route::apiResource('addresses', AddressController::class);
        Route::put('addresses/{id}/default', [AddressController::class, 'setDefault']);

        Route::get('favorites', [FavoriteController::class, 'index']);
        Route::post('favorites/{productId}', [FavoriteController::class, 'add']);
        Route::delete('favorites/{productId}', [FavoriteController::class, 'remove']);

        Route::post('checkout', [CheckoutController::class, 'create']);

        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{id}', [OrderController::class, 'show']);
        Route::get('orders/{id}/tracking', [OrderTrackingController::class, 'show']);

        Route::post('payments/initialize', [PaymentController::class, 'initialize']);
        Route::get('payments/{ref}/status', [PaymentController::class, 'status']);
    });

    // Webhooks publics (signature HMAC vérifiée à l'intérieur)
    Route::post('webhooks/cinetpay', [PaymentController::class, 'webhook']);
});
```

---

## 3. Sprint 2 — Endpoints API manquants ✅ TERMINÉ

### 3.1 Migration favorites

```php
Schema::create('favorites', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
    $table->unique(['user_id', 'product_id']);
});
```

### 3.2 FavoriteController

```php
// app/Http/Controllers/Api/V1/FavoriteController.php
public function index(Request $request)
{
    $products = Product::whereIn('id', function ($q) use ($request) {
        $q->select('product_id')
          ->from('favorites')
          ->where('user_id', $request->user()->id);
    })->get();

    return $this->ok(ProductResource::collection($products));
}

public function add(Request $request, $productId)
{
    Favorite::firstOrCreate([
        'user_id' => $request->user()->id,
        'product_id' => $productId,
    ]);
    return $this->ok(['added' => true]);
}

public function remove(Request $request, $productId)
{
    Favorite::where('user_id', $request->user()->id)
        ->where('product_id', $productId)
        ->delete();
    return $this->ok(['removed' => true]);
}
```

### 3.3 CheckoutController + CheckoutService

Voir le code complet dans ton `IMPLEMENT.md` section 6.5. Adapte juste pour utiliser ton `User` actuel (id auto-increment) au lieu du Firebase UID en string.

**Points d'attention** :
- Transaction DB obligatoire avec `lockForUpdate()` sur les produits
- Snapshots dans `order_items` (nom, prix, image au moment de la commande)
- Décrément du stock dans la même transaction
- Gérer les frais de livraison **par zone** dès maintenant (cf. section 14.3 plus bas)

```php
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

                // Appliquer le tarif B2B si applicable
                $price = $this->resolvePrice($product, $user);
                $lineTotal = $price * $item['quantity'];
                $totalPrice += $lineTotal;

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price' => $price,
                    'quantity' => $item['quantity'],
                    'option' => $item['option'] ?? null,
                    'image_url' => $product->images[0] ?? null,
                ];
            }

            $order = Order::create([
                'id' => 'order_' . Str::random(12),
                'user_id' => $user->id,
                'total_price' => $totalPrice,
                'delivery_fee' => $deliveryFee,
                'total_amount' => $totalPrice + $deliveryFee,
                'delivery_address' => "{$address->detail}, {$address->city}",
                'address_id' => $address->id,
                'payment_method' => $paymentMethod,
                'payment_status' => 'pending',
                'status' => 'pending',
                'ordered_at' => now(),
            ]);

            $order->items()->createMany($orderItemsData);

            OrderStatusHistory::create([
                'order_id' => $order->id,
                'status' => 'pending',
                'completed_at' => now(),
            ]);

            return $order;
        });
    }

    private function resolvePrice(Product $product, User $user): int
    {
        // B2B avec tarification spéciale
        if ($user->account_type === 'b2b' && $user->b2bAccount) {
            $custom = $user->b2bAccount->customPrices()->where('product_id', $product->id)->first();
            if ($custom) return $custom->price;
            // Sinon réduction globale B2B
            return (int) round($product->price * (1 - $user->b2bAccount->discount_rate / 100));
        }

        // Premium : 5% de réduction
        if ($user->is_premium) {
            return (int) round($product->price * 0.95);
        }

        return $product->price;
    }

    private function computeDeliveryFee(string $city, array $items): int
    {
        $zone = DeliveryZone::where('city', $city)->first();
        if (!$zone) return 1500; // fallback

        $totalAmount = collect($items)->sum(fn($i) => $i['quantity'] * Product::find($i['product_id'])->price);
        if ($totalAmount >= $zone->free_delivery_threshold) return 0;

        return $zone->fee;
    }
}
```

### 3.4 OrderTrackingController

```php
// app/Http/Controllers/Api/V1/OrderTrackingController.php
public function show(Request $request, string $id)
{
    $order = Order::with('courier')
        ->where('user_id', $request->user()->id)
        ->findOrFail($id);

    $history = OrderStatusHistory::where('order_id', $id)->get()->keyBy('status');

    $steps = [
        [
            'step' => 'Commande reçue',
            'subtitle' => 'Votre commande a été confirmée',
            'completed' => $history->has('confirmed') || $history->has('pending'),
            'completedAt' => ($history->get('confirmed') ?? $history->get('pending'))?->completed_at?->toIso8601String(),
        ],
        [
            'step' => 'En préparation',
            'subtitle' => 'Nous préparons votre viande',
            'completed' => $history->has('preparing'),
            'completedAt' => $history->get('preparing')?->completed_at?->toIso8601String(),
        ],
        [
            'step' => 'En livraison',
            'subtitle' => 'Votre livreur est en route',
            'completed' => $history->has('delivering'),
            'completedAt' => $history->get('delivering')?->completed_at?->toIso8601String(),
        ],
        [
            'step' => 'Livrée',
            'subtitle' => 'Commande livrée',
            'completed' => $history->has('delivered'),
            'completedAt' => $history->get('delivered')?->completed_at?->toIso8601String(),
        ],
    ];

    return $this->ok([
        'steps' => $steps,
        'courier' => $order->courier ? [
            'id' => $order->courier->id,
            'name' => $order->courier->name,
            'phone' => $order->courier->phone,
            'photoUrl' => $order->courier->photo_url,
            'rating' => (float) $order->courier->rating,
            'vehicle' => $order->courier->vehicle,
            'currentLat' => $order->courier->current_lat,
            'currentLng' => $order->courier->current_lng,
        ] : null,
        'eta' => $order->eta?->toIso8601String(),
    ]);
}
```

⚠️ **Ne change jamais** les libellés `step` "reçue", "préparation", "livraison", "livrée" — le Flutter parse ces mots pour choisir l'icône.

### 3.5 AddressController — retour liste complète

C'est le piège classique. Toutes les mutations doivent renvoyer la liste complète des adresses du user, pas juste l'objet modifié :

```php
private function listResponse(int $userId)
{
    return $this->ok(
        AddressResource::collection(
            Address::where('user_id', $userId)->orderByDesc('is_default')->get()
        )
    );
}

public function store(StoreAddressRequest $request)
{
    Address::create($request->validated() + ['user_id' => $request->user()->id]);
    return $this->listResponse($request->user()->id);
}

public function update(UpdateAddressRequest $request, $id)
{
    $address = Address::where('user_id', $request->user()->id)->findOrFail($id);
    $address->update($request->validated());
    return $this->listResponse($request->user()->id);
}

public function destroy(Request $request, $id)
{
    Address::where('user_id', $request->user()->id)->findOrFail($id)->delete();
    return $this->listResponse($request->user()->id);
}

public function setDefault(Request $request, $id)
{
    $userId = $request->user()->id;
    DB::transaction(function () use ($userId, $id) {
        Address::where('user_id', $userId)->update(['is_default' => false]);
        Address::where('user_id', $userId)->where('id', $id)->update(['is_default' => true]);
    });
    return $this->listResponse($userId);
}
```

---

## 4. Sprint 3 — Notifications push & uploads ✅ TERMINÉ

### 4.1 Service FCM

```php
// app/Services/FirebaseMessagingService.php
namespace App\Services;

use Kreait\Laravel\Firebase\Facades\Firebase;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Log;

class FirebaseMessagingService
{
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        try {
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification(Notification::create($title, $body))
                ->withData(array_map('strval', $data));

            Firebase::messaging()->send($message);
            return true;
        } catch (\Throwable $e) {
            Log::error('FCM send failed', ['error' => $e->getMessage(), 'token' => substr($token, 0, 20)]);
            return false;
        }
    }

    public function sendToTopic(string $topic, string $title, string $body, array $data = []): bool
    {
        try {
            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification(Notification::create($title, $body))
                ->withData(array_map('strval', $data));

            Firebase::messaging()->send($message);
            return true;
        } catch (\Throwable $e) {
            Log::error('FCM topic send failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function sendToMany(array $tokens, string $title, string $body, array $data = []): array
    {
        try {
            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withData(array_map('strval', $data));

            $report = Firebase::messaging()->sendMulticast($message, $tokens);
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
```

### 4.2 Job d'envoi statut commande

```php
// app/Jobs/SendOrderStatusNotification.php
namespace App\Jobs;

use App\Models\Order;
use App\Models\Notification as NotifLog;
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
            'type' => 'order_status',
            'order_id' => $this->order->id,
            'status' => $this->status,
        ]);

        NotifLog::create([
            'user_id' => $this->order->user_id,
            'title' => $title,
            'body' => $body,
            'type' => 'order_status',
            'data' => ['order_id' => $this->order->id, 'status' => $this->status],
            'sent' => $sent,
            'sent_at' => now(),
        ]);
    }
}
```

### 4.3 Migration table notifications (log)

```php
Schema::create('notifications', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->text('body');
    $table->string('type'); // order_status, promo, system
    $table->json('data')->nullable();
    $table->boolean('sent')->default(false);
    $table->boolean('read')->default(false);
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('read_at')->nullable();
    $table->timestamps();

    $table->index(['user_id', 'read']);
});
```

### 4.4 Configuration queue Redis

`.env` :
```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

Worker en prod (via Supervisor) :
```ini
; /etc/supervisor/conf.d/boucherie-queue.conf
[program:boucherie-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/boucherie/artisan queue:work --queue=default --tries=3 --max-time=3600
numprocs=2
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/boucherie/queue.log
```

### 4.5 Seeders réalistes (essentiel pour la démo)

```php
// database/seeders/CategorySeeder.php
public function run()
{
    $categories = [
        ['id' => 'cat_beef', 'name' => 'Bœuf', 'icon' => '🥩', 'sort_order' => 1],
        ['id' => 'cat_chicken', 'name' => 'Poulet', 'icon' => '🍗', 'sort_order' => 2],
        ['id' => 'cat_lamb', 'name' => 'Mouton', 'icon' => '🐑', 'sort_order' => 3],
        ['id' => 'cat_fish', 'name' => 'Poisson', 'icon' => '🐟', 'sort_order' => 4],
        ['id' => 'cat_offal', 'name' => 'Abats', 'icon' => '🫀', 'sort_order' => 5],
        ['id' => 'cat_processed', 'name' => 'Transformés', 'icon' => '🌭', 'sort_order' => 6],
    ];
    foreach ($categories as $c) Category::updateOrCreate(['id' => $c['id']], $c);
}
```

Pour le ProductSeeder, **utilise de vraies photos** (au moins 15-20 produits). Mauvaise option : Lorem Picsum. Bonne option : photos libres de droits depuis Unsplash (recherche "beef", "chicken meat", "lamb chops"), ou photos de tes vrais fournisseurs si tu en as déjà. Mets-les dans `storage/app/public/products/` et `php artisan storage:link`.

```php
// database/seeders/ProductSeeder.php (extrait)
$products = [
    [
        'name' => 'Filet de bœuf',
        'description' => 'Morceau noble, tendre et savoureux. Idéal grillé ou en pavé.',
        'price' => 8500,
        'category_id' => 'cat_beef',
        'preparation_options' => ['Entier', 'Tranché', 'Pavé'],
        'is_bio' => false,
        'is_halal' => true,
        'is_fresh' => true,
        'farm_name' => 'Ferme Adjamé',
        'stock' => 25,
        'unit' => 'kg',
        'images' => ['/storage/products/beef-filet-1.jpg'],
    ],
    // ... 15-20 produits crédibles
];
```

---

## 5. Sprint 4 — Paiements CinetPay

### 5.1 Configuration

```env
CINETPAY_API_KEY=your_key
CINETPAY_SITE_ID=your_site
CINETPAY_SECRET_KEY=your_secret
CINETPAY_NOTIFY_URL="${APP_URL}/api/v1/webhooks/cinetpay"
CINETPAY_RETURN_URL=https://app.boucherie-express.ci/payment-return
```

`config/services.php` :
```php
'cinetpay' => [
    'api_key' => env('CINETPAY_API_KEY'),
    'site_id' => env('CINETPAY_SITE_ID'),
    'secret_key' => env('CINETPAY_SECRET_KEY'),
    'notify_url' => env('CINETPAY_NOTIFY_URL'),
    'return_url' => env('CINETPAY_RETURN_URL'),
],
```

### 5.2 CinetPayService

```php
// app/Services/CinetPayService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CinetPayService
{
    private string $baseUrl = 'https://api-checkout.cinetpay.com/v2';

    public function initialize(int $amount, string $transactionId, string $description, string $customerPhone, string $customerName): array
    {
        $response = Http::post("{$this->baseUrl}/payment", [
            'apikey' => config('services.cinetpay.api_key'),
            'site_id' => config('services.cinetpay.site_id'),
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => 'XOF',
            'description' => $description,
            'notify_url' => config('services.cinetpay.notify_url'),
            'return_url' => config('services.cinetpay.return_url'),
            'channels' => 'MOBILE_MONEY',
            'customer_phone_number' => $customerPhone,
            'customer_name' => $customerName,
        ]);

        Log::info('CinetPay init', ['tx' => $transactionId, 'status' => $response->status()]);
        return $response->json();
    }

    public function checkStatus(string $transactionId): array
    {
        $response = Http::post("{$this->baseUrl}/payment/check", [
            'apikey' => config('services.cinetpay.api_key'),
            'site_id' => config('services.cinetpay.site_id'),
            'transaction_id' => $transactionId,
        ]);
        return $response->json();
    }

    public function verifyHmac(string $payload, string $signature): bool
    {
        $expected = hash_hmac('sha256', $payload, config('services.cinetpay.secret_key'));
        return hash_equals($expected, $signature);
    }
}
```

### 5.3 PaymentController complet

```php
// app/Http/Controllers/Api/V1/PaymentController.php
public function initialize(Request $request, CinetPayService $cinetpay)
{
    $request->validate(['order_id' => 'required|exists:orders,id']);

    $order = Order::with('user')->where('user_id', $request->user()->id)
        ->findOrFail($request->input('order_id'));

    if ($order->payment_status === 'paid') {
        return $this->fail('Cette commande est déjà payée', 422);
    }

    $transactionId = 'tx_' . Str::random(16);
    $result = $cinetpay->initialize(
        amount: $order->total_amount,
        transactionId: $transactionId,
        description: "Boucherie Express #{$order->id}",
        customerPhone: $order->user->phone,
        customerName: $order->user->name,
    );

    if (($result['code'] ?? null) !== '201') {
        Log::error('CinetPay init failed', ['result' => $result]);
        return $this->fail('Échec de l\'initialisation du paiement', 422);
    }

    $order->update(['payment_reference' => $transactionId]);

    Payment::create([
        'order_id' => $order->id,
        'reference' => $transactionId,
        'amount' => $order->total_amount,
        'method' => $order->payment_method,
        'status' => 'pending',
    ]);

    return $this->ok([
        'paymentUrl' => $result['data']['payment_url'],
        'paymentToken' => $result['data']['payment_token'] ?? null,
        'reference' => $transactionId,
    ]);
}

public function status(Request $request, string $ref, CinetPayService $cinetpay)
{
    $payment = Payment::where('reference', $ref)->firstOrFail();
    return $this->ok([
        'reference' => $payment->reference,
        'status' => $payment->status,
        'amount' => $payment->amount,
    ]);
}

public function webhook(Request $request, CinetPayService $cinetpay)
{
    // Vérifier la signature HMAC
    $signature = $request->header('X-Token') ?? $request->input('x-token');
    $payload = $request->getContent();
    if (!$signature || !$cinetpay->verifyHmac($payload, $signature)) {
        Log::warning('CinetPay webhook bad HMAC', ['ip' => $request->ip()]);
        return response('Invalid signature', 403);
    }

    $transactionId = $request->input('cpm_trans_id');
    if (!$transactionId) return response('Bad Request', 400);

    $status = $cinetpay->checkStatus($transactionId);
    $order = Order::where('payment_reference', $transactionId)->first();
    if (!$order) return response('Order not found', 404);

    $payment = Payment::where('reference', $transactionId)->first();

    if (($status['code'] ?? null) === '00') {
        $order->update(['payment_status' => 'paid', 'status' => 'confirmed']);
        if ($payment) $payment->update(['status' => 'success', 'paid_at' => now()]);

        OrderStatusHistory::create([
            'order_id' => $order->id,
            'status' => 'confirmed',
            'completed_at' => now(),
        ]);

        SendOrderStatusNotification::dispatch($order, 'confirmed');
    } else {
        $order->update(['payment_status' => 'failed']);
        if ($payment) $payment->update(['status' => 'failed']);
    }

    return response('OK', 200);
}
```

### 5.4 Migration payments

```php
Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->string('order_id');
    $table->string('reference')->unique();
    $table->integer('amount');
    $table->enum('method', ['orange_money', 'mtn_momo', 'wave', 'moov', 'cash']);
    $table->enum('status', ['pending', 'success', 'failed', 'refunded'])->default('pending');
    $table->json('gateway_response')->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();

    $table->foreign('order_id')->references('id')->on('orders');
    $table->index('status');
});
```

---

## 6. Sprint 5 — Backoffice Filament exhaustif (PRIORITÉ) ✅ TERMINÉ

### 6.1 Architecture du panel admin

```
app/Filament/
├── Resources/
│   ├── ProductResource.php (+ Pages, RelationManagers)
│   ├── CategoryResource.php
│   ├── OrderResource.php
│   ├── CustomerResource.php (User filtré b2c)
│   ├── B2BAccountResource.php
│   ├── CourierResource.php
│   ├── SupplierResource.php
│   ├── PaymentResource.php
│   ├── CouponResource.php
│   ├── ProductBatchResource.php
│   ├── DeliveryZoneResource.php
│   ├── NotificationResource.php
│   └── RoleResource.php
├── Pages/
│   ├── Dashboard.php (custom)
│   ├── Analytics.php
│   ├── CourierMap.php
│   └── Settings.php
└── Widgets/
    ├── RevenueOverview.php
    ├── OrdersChart.php
    ├── TopProducts.php
    ├── LowStockAlert.php
    ├── ActiveCouriers.php
    ├── RecentOrders.php
    └── NewCustomers.php
```

### 6.2 Dashboard principal — Widgets détaillés

#### 6.2.1 Widget RevenueOverview (KPIs en haut)

```php
// app/Filament/Widgets/RevenueOverview.php
namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Order;
use App\Models\User;

class RevenueOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $today = Order::whereDate('ordered_at', today())
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        $yesterday = Order::whereDate('ordered_at', today()->subDay())
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        $delta = $yesterday > 0 ? round((($today - $yesterday) / $yesterday) * 100, 1) : 0;

        $monthRevenue = Order::whereMonth('ordered_at', now()->month)
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        $avgBasket = Order::whereMonth('ordered_at', now()->month)
            ->where('payment_status', 'paid')
            ->avg('total_amount') ?? 0;

        $newCustomers = User::whereMonth('created_at', now()->month)
            ->where('account_type', 'b2c')
            ->count();

        $pendingOrders = Order::whereIn('status', ['pending', 'confirmed', 'preparing'])->count();

        return [
            Stat::make('CA Aujourd\'hui', number_format($today, 0, ',', ' ') . ' FCFA')
                ->description(($delta >= 0 ? '+' : '') . $delta . '% vs hier')
                ->descriptionIcon($delta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($delta >= 0 ? 'success' : 'danger')
                ->chart($this->lastSevenDays()),

            Stat::make('CA du mois', number_format($monthRevenue, 0, ',', ' ') . ' FCFA')
                ->description('Mois en cours')
                ->color('primary'),

            Stat::make('Panier moyen', number_format($avgBasket, 0, ',', ' ') . ' FCFA')
                ->description('Cible : 9 000 FCFA')
                ->color($avgBasket >= 9000 ? 'success' : 'warning'),

            Stat::make('Nouveaux clients', $newCustomers)
                ->description('Ce mois-ci')
                ->descriptionIcon('heroicon-m-user-plus'),

            Stat::make('Commandes en cours', $pendingOrders)
                ->description('À traiter')
                ->color($pendingOrders > 10 ? 'warning' : 'success')
                ->descriptionIcon('heroicon-m-clock'),
        ];
    }

    private function lastSevenDays(): array
    {
        return collect(range(6, 0))->map(function ($d) {
            return (int) Order::whereDate('ordered_at', today()->subDays($d))
                ->where('payment_status', 'paid')
                ->sum('total_amount');
        })->toArray();
    }
}
```

#### 6.2.2 Widget OrdersChart

```php
// app/Filament/Widgets/OrdersChart.php
namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrdersChart extends ChartWidget
{
    protected static ?string $heading = 'Commandes 30 derniers jours';
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 2;

    protected function getData(): array
    {
        $data = Order::select(DB::raw('DATE(ordered_at) as date'), DB::raw('count(*) as count'), DB::raw('sum(total_amount) as revenue'))
            ->where('ordered_at', '>=', now()->subDays(30))
            ->groupBy('date')->orderBy('date')->get();

        return [
            'datasets' => [
                [
                    'label' => 'Commandes',
                    'data' => $data->pluck('count')->toArray(),
                    'borderColor' => '#dc2626',
                    'backgroundColor' => 'rgba(220, 38, 38, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $data->pluck('date')->map(fn($d) => \Carbon\Carbon::parse($d)->format('d/m'))->toArray(),
        ];
    }

    protected function getType(): string { return 'line'; }
}
```

#### 6.2.3 Widget TopProducts

```php
// app/Filament/Widgets/TopProducts.php
class TopProducts extends BaseWidget
{
    protected static ?string $heading = 'Top 10 produits du mois';
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::select('products.*', DB::raw('SUM(order_items.quantity) as total_sold'), DB::raw('SUM(order_items.quantity * order_items.price) as revenue'))
                    ->join('order_items', 'order_items.product_id', '=', 'products.id')
                    ->join('orders', 'orders.id', '=', 'order_items.order_id')
                    ->whereMonth('orders.ordered_at', now()->month)
                    ->where('orders.payment_status', 'paid')
                    ->groupBy('products.id')
                    ->orderByDesc('total_sold')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('total_sold')->label('Vendus')->suffix(' kg'),
                TextColumn::make('revenue')->label('CA')->money('XOF', divideBy: 1),
                TextColumn::make('stock')->badge()
                    ->color(fn($state) => $state < 5 ? 'danger' : ($state < 15 ? 'warning' : 'success')),
            ]);
    }
}
```

#### 6.2.4 Widget LowStockAlert

```php
class LowStockAlert extends BaseWidget
{
    protected static ?int $sort = 4;

    public function table(Table $table): Table
    {
        return $table
            ->query(Product::where('stock', '<', 10)->where('is_active', true))
            ->heading('⚠️ Alertes stock bas')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('stock')->badge()->color('danger'),
                TextColumn::make('category.name')->label('Catégorie'),
            ])
            ->actions([
                Action::make('restock')->label('Réapprovisionner')
                    ->form([TextInput::make('quantity')->numeric()->required()])
                    ->action(fn ($record, $data) => $record->increment('stock', $data['quantity']))
            ]);
    }
}
```

### 6.3 ProductResource — exhaustif

```php
// app/Filament/Resources/ProductResource.php
public static function form(Form $form): Form
{
    return $form->schema([
        Tabs::make('Product')->tabs([

            Tabs\Tab::make('Informations')->schema([
                Section::make()->schema([
                    TextInput::make('name')->required()->maxLength(150),
                    Textarea::make('description')->rows(4),
                    Select::make('category_id')->relationship('category', 'name')
                        ->required()->searchable()->preload(),
                    TextInput::make('farm_name')->label('Origine / Ferme'),
                ])->columns(2),
            ]),

            Tabs\Tab::make('Prix & stock')->schema([
                Grid::make(3)->schema([
                    TextInput::make('price')->required()->numeric()->suffix('FCFA'),
                    TextInput::make('old_price')->numeric()->suffix('FCFA')->label('Prix barré (optionnel)'),
                    TextInput::make('stock')->required()->numeric()->default(0),
                    Select::make('unit')->options(['kg' => 'kg', 'unit' => 'unité', 'pack' => 'pack'])->default('kg'),
                    TextInput::make('min_order_quantity')->numeric()->default(1)->label('Quantité min'),
                    TextInput::make('low_stock_threshold')->numeric()->default(5)->label('Seuil alerte stock'),
                ]),
            ]),

            Tabs\Tab::make('Médias')->schema([
                FileUpload::make('images')->multiple()->image()
                    ->directory('products')->reorderable()->maxFiles(5),
                TextInput::make('video_url')->url()->label('URL vidéo (YouTube/Vimeo)'),
            ]),

            Tabs\Tab::make('Caractéristiques')->schema([
                Grid::make(3)->schema([
                    Toggle::make('is_active')->default(true),
                    Toggle::make('is_fresh')->label('Frais')->default(true),
                    Toggle::make('is_bio')->label('Bio'),
                    Toggle::make('is_halal')->label('Halal certifié')->default(true),
                    Toggle::make('is_promoted')->label('En promotion'),
                    Toggle::make('is_featured')->label('Mis en avant'),
                ]),
                TagsInput::make('preparation_options')
                    ->label('Options de découpe')
                    ->placeholder('Entier, Découpé, Haché, Tranché...'),
                TagsInput::make('tags')->label('Tags marketing'),
            ]),

            Tabs\Tab::make('Traçabilité')->schema([
                Select::make('supplier_id')->relationship('supplier', 'name')->searchable(),
                DatePicker::make('slaughter_date')->label('Date d\'abattage'),
                TextInput::make('lot_number')->label('Numéro de lot'),
                Textarea::make('storage_conditions')->rows(2)->label('Conditions de conservation'),
            ]),

            Tabs\Tab::make('Tarification B2B')->schema([
                Repeater::make('b2b_prices')->schema([
                    Select::make('b2b_account_id')->relationship('b2bAccount', 'name')->required(),
                    TextInput::make('price')->numeric()->required()->suffix('FCFA'),
                ])->relationship('b2bPrices')->collapsible(),
            ]),

        ])->columnSpanFull(),
    ]);
}

public static function table(Table $table): Table
{
    return $table
        ->columns([
            ImageColumn::make('images.0')->label('')->circular(),
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('category.name')->badge()->sortable(),
            TextColumn::make('price')->money('XOF', divideBy: 1)->sortable(),
            TextColumn::make('stock')->badge()
                ->color(fn ($state, $record) =>
                    $state <= 0 ? 'danger' :
                    ($state < $record->low_stock_threshold ? 'warning' : 'success')
                ),
            IconColumn::make('is_active')->boolean(),
            IconColumn::make('is_halal')->label('Halal')->boolean(),
            IconColumn::make('is_bio')->label('Bio')->boolean(),
        ])
        ->filters([
            SelectFilter::make('category_id')->relationship('category', 'name'),
            TernaryFilter::make('is_active'),
            TernaryFilter::make('is_halal'),
            TernaryFilter::make('is_bio'),
            Filter::make('low_stock')
                ->query(fn (Builder $q) => $q->whereColumn('stock', '<', 'low_stock_threshold'))
                ->label('Stock bas'),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->action(fn ($record) => $record->replicate()->save()),
            Tables\Actions\Action::make('restock')
                ->icon('heroicon-o-arrow-path')
                ->form([TextInput::make('quantity')->numeric()->required()])
                ->action(fn ($record, $data) => $record->increment('stock', $data['quantity'])),
        ])
        ->bulkActions([
            Tables\Actions\BulkAction::make('activate')->action(fn ($records) => $records->each->update(['is_active' => true])),
            Tables\Actions\BulkAction::make('deactivate')->action(fn ($records) => $records->each->update(['is_active' => false])),
            Tables\Actions\ExportBulkAction::make(),
        ]);
}

public static function getRelations(): array
{
    return [
        SalesHistoryRelationManager::class, // historique des ventes du produit
        StockMovementsRelationManager::class, // mouvements de stock
    ];
}
```

### 6.4 OrderResource — workflow complet

```php
// app/Filament/Resources/OrderResource.php
public static function form(Form $form): Form
{
    return $form->schema([
        Section::make('Client')->schema([
            TextInput::make('user.name')->disabled(),
            TextInput::make('user.phone')->disabled(),
            TextInput::make('user.email')->disabled(),
        ])->columns(3),

        Section::make('Statut')->schema([
            Select::make('status')->options([
                'pending' => 'En attente',
                'confirmed' => 'Confirmée',
                'preparing' => 'En préparation',
                'delivering' => 'En livraison',
                'delivered' => 'Livrée',
                'cancelled' => 'Annulée',
            ])->required()
              ->reactive()
              ->afterStateUpdated(function ($state, $record) {
                  if ($record && $state !== $record->status) {
                      OrderStatusHistory::create(['order_id' => $record->id, 'status' => $state, 'completed_at' => now()]);
                      SendOrderStatusNotification::dispatch($record, $state);
                  }
              }),
            Select::make('courier_id')->relationship('courier', 'name')->searchable()->label('Livreur'),
            Select::make('payment_status')->options([
                'pending' => 'En attente',
                'paid' => 'Payée',
                'failed' => 'Échec',
                'refunded' => 'Remboursée',
            ]),
            DateTimePicker::make('eta')->label('Heure de livraison estimée'),
        ])->columns(2),

        Section::make('Livraison')->schema([
            Textarea::make('delivery_address')->rows(2),
            Textarea::make('note')->label('Note du client')->rows(2),
        ]),
    ]);
}

public static function table(Table $table): Table
{
    return $table
        ->defaultSort('ordered_at', 'desc')
        ->columns([
            TextColumn::make('id')->label('Cmd #')->searchable(),
            TextColumn::make('user.name')->searchable()->label('Client'),
            TextColumn::make('items_count')->counts('items')->label('Articles'),
            TextColumn::make('total_amount')->money('XOF', divideBy: 1),
            TextColumn::make('status')->badge()
                ->colors([
                    'gray' => 'pending',
                    'info' => 'confirmed',
                    'warning' => 'preparing',
                    'primary' => 'delivering',
                    'success' => 'delivered',
                    'danger' => 'cancelled',
                ]),
            TextColumn::make('payment_status')->badge()
                ->colors([
                    'warning' => 'pending',
                    'success' => 'paid',
                    'danger' => 'failed',
                ]),
            TextColumn::make('payment_method')->badge(),
            TextColumn::make('courier.name')->label('Livreur')->placeholder('—'),
            TextColumn::make('ordered_at')->dateTime('d/m H:i')->sortable(),
        ])
        ->filters([
            SelectFilter::make('status')->multiple(),
            SelectFilter::make('payment_status'),
            SelectFilter::make('payment_method'),
            Filter::make('today')->query(fn ($q) => $q->whereDate('ordered_at', today()))->default(),
            Filter::make('last_week')->query(fn ($q) => $q->where('ordered_at', '>=', now()->subWeek())),
            SelectFilter::make('courier_id')->relationship('courier', 'name'),
        ])
        ->actions([
            ViewAction::make(),
            EditAction::make(),
            Action::make('assign_courier')->icon('heroicon-o-truck')
                ->visible(fn ($record) => !$record->courier_id && $record->status === 'preparing')
                ->form([Select::make('courier_id')->options(Courier::where('is_active', true)->pluck('name', 'id'))->required()])
                ->action(fn ($record, $data) => $record->update(['courier_id' => $data['courier_id'], 'status' => 'delivering'])),
            Action::make('refund')->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn ($record) => $record->payment_status === 'paid' && $record->status !== 'delivered')
                ->requiresConfirmation()
                ->action(fn ($record) => $record->update(['status' => 'cancelled', 'payment_status' => 'refunded'])),
            Action::make('print_invoice')->icon('heroicon-o-printer')
                ->url(fn ($record) => route('orders.invoice', $record->id))->openUrlInNewTab(),
        ])
        ->headerActions([
            ExportAction::make(), // export CSV des commandes filtrées
        ]);
}

public static function getRelations(): array
{
    return [
        OrderItemsRelationManager::class,
        StatusHistoryRelationManager::class,
        PaymentsRelationManager::class,
    ];
}
```

### 6.5 CustomerResource (vue B2C)

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->where('account_type', 'b2c');
}

public static function table(Table $table): Table
{
    return $table->columns([
        ImageColumn::make('photo_url')->circular()->label(''),
        TextColumn::make('name')->searchable(),
        TextColumn::make('phone')->searchable(),
        TextColumn::make('email')->searchable(),
        TextColumn::make('orders_count')->counts('orders')->label('Cmd'),
        TextColumn::make('total_spent')
            ->label('Dépensé')
            ->getStateUsing(fn($record) => $record->orders()->where('payment_status', 'paid')->sum('total_amount'))
            ->money('XOF', divideBy: 1)
            ->sortable(),
        TextColumn::make('last_order')
            ->label('Dernière cmd')
            ->getStateUsing(fn($record) => $record->orders()->latest('ordered_at')->first()?->ordered_at?->diffForHumans()),
        IconColumn::make('is_premium')->boolean()->label('Premium'),
        TextColumn::make('created_at')->date()->label('Inscrit le'),
    ])
    ->filters([
        TernaryFilter::make('is_premium'),
        Filter::make('high_value')->query(fn($q) =>
            $q->whereHas('orders', fn($o) =>
                $o->select(DB::raw('sum(total_amount)'))->groupBy('user_id')->havingRaw('sum(total_amount) > 50000'))),
    ])
    ->actions([
        ViewAction::make(),
        Action::make('send_notification')->icon('heroicon-o-bell')
            ->form([
                TextInput::make('title')->required(),
                Textarea::make('body')->required(),
            ])
            ->action(function ($record, $data) {
                if ($record->fcm_token) {
                    app(FirebaseMessagingService::class)->sendToToken(
                        $record->fcm_token, $data['title'], $data['body']
                    );
                }
            }),
        Action::make('grant_premium')->icon('heroicon-o-star')
            ->visible(fn($record) => !$record->is_premium)
            ->form([DatePicker::make('until')->required()->default(now()->addYear())])
            ->action(fn($record, $data) => $record->update(['is_premium' => true, 'premium_until' => $data['until']])),
    ]);
}

public static function infolist(Infolist $infolist): Infolist
{
    return $infolist->schema([
        Section::make('Profil')->schema([
            TextEntry::make('name'),
            TextEntry::make('phone'),
            TextEntry::make('email'),
            TextEntry::make('created_at')->dateTime(),
        ])->columns(2),

        Section::make('Statistiques')->schema([
            TextEntry::make('total_spent')
                ->getStateUsing(fn($record) => number_format($record->orders()->where('payment_status', 'paid')->sum('total_amount'), 0, ',', ' ') . ' FCFA'),
            TextEntry::make('avg_basket')
                ->getStateUsing(fn($record) => number_format($record->orders()->where('payment_status', 'paid')->avg('total_amount') ?? 0, 0, ',', ' ') . ' FCFA'),
            TextEntry::make('orders_count')->getStateUsing(fn($record) => $record->orders()->count()),
            TextEntry::make('frequency')->label('Fréquence (cmd/mois)')->getStateUsing(function($record) {
                $months = $record->created_at->diffInMonths(now()) ?: 1;
                return round($record->orders()->count() / $months, 1);
            }),
        ])->columns(4),
    ]);
}

public static function getRelations(): array
{
    return [
        AddressesRelationManager::class,
        OrdersRelationManager::class,
        FavoritesRelationManager::class,
    ];
}
```

### 6.6 CourierResource

```php
public static function form(Form $form): Form
{
    return $form->schema([
        FileUpload::make('photo_url')->image()->avatar()->directory('couriers'),
        TextInput::make('name')->required(),
        TextInput::make('phone')->required()->tel(),
        TextInput::make('vehicle')->default('Moto'),
        TextInput::make('license_plate')->label('Plaque d\'immatriculation'),
        TextInput::make('rating')->numeric()->default(5.0)->step(0.1)->disabled(),
        Toggle::make('is_active')->default(true),
        Toggle::make('is_available')->default(true)->label('Disponible maintenant'),
        Select::make('zones')->multiple()->relationship('zones', 'name')->label('Zones desservies'),
    ]);
}

public static function table(Table $table): Table
{
    return $table->columns([
        ImageColumn::make('photo_url')->circular(),
        TextColumn::make('name')->searchable(),
        TextColumn::make('phone'),
        TextColumn::make('rating')->badge()->color('warning')->prefix('⭐ '),
        TextColumn::make('total_deliveries')
            ->getStateUsing(fn($record) => Order::where('courier_id', $record->id)->where('status', 'delivered')->count())
            ->label('Livraisons'),
        TextColumn::make('active_deliveries')
            ->getStateUsing(fn($record) => Order::where('courier_id', $record->id)->where('status', 'delivering')->count())
            ->badge()->label('En cours'),
        IconColumn::make('is_active')->boolean(),
        IconColumn::make('is_available')->boolean()->label('Dispo'),
    ]);
}
```

### 6.7 Page custom : carte des livreurs en temps réel

```php
// app/Filament/Pages/CourierMap.php
namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Courier;
use App\Models\Order;

class CourierMap extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $title = 'Carte livreurs';
    protected static string $view = 'filament.pages.courier-map';
    protected static ?int $navigationSort = 8;

    public function getCouriersData(): array
    {
        return Courier::where('is_active', true)
            ->whereNotNull('current_lat')
            ->with(['activeOrder.user'])
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'lat' => (float) $c->current_lat,
                'lng' => (float) $c->current_lng,
                'photo' => $c->photo_url,
                'order' => $c->activeOrder ? [
                    'id' => $c->activeOrder->id,
                    'customer' => $c->activeOrder->user->name,
                    'address' => $c->activeOrder->delivery_address,
                ] : null,
                'last_update' => $c->location_updated_at?->diffForHumans(),
            ])->toArray();
    }
}
```

Vue Blade (`resources/views/filament/pages/courier-map.blade.php`) avec Leaflet + OSM (gratuit, pas de clé API à gérer) — le code détaillé sera dans le sprint 8.

### 6.8 CouponResource (promotions)

```php
// Migration
Schema::create('coupons', function (Blueprint $table) {
    $table->id();
    $table->string('code')->unique();
    $table->enum('type', ['percent', 'fixed', 'free_delivery']);
    $table->integer('value'); // % ou FCFA selon type
    $table->integer('min_order_amount')->default(0);
    $table->integer('max_uses')->nullable();
    $table->integer('uses_count')->default(0);
    $table->integer('max_uses_per_user')->default(1);
    $table->timestamp('starts_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->boolean('is_active')->default(true);
    $table->json('applicable_categories')->nullable();
    $table->json('applicable_products')->nullable();
    $table->boolean('first_order_only')->default(false);
    $table->boolean('premium_only')->default(false);
    $table->timestamps();
});

// Filament Resource form
TextInput::make('code')->required()->unique()->placeholder('BIENVENUE10'),
Select::make('type')->options([
    'percent' => 'Pourcentage', 'fixed' => 'Montant fixe', 'free_delivery' => 'Livraison gratuite',
])->reactive()->required(),
TextInput::make('value')->numeric()->required()
    ->suffix(fn($get) => $get('type') === 'percent' ? '%' : 'FCFA'),
TextInput::make('min_order_amount')->numeric()->suffix('FCFA'),
TextInput::make('max_uses')->numeric()->placeholder('Illimité'),
TextInput::make('max_uses_per_user')->numeric()->default(1),
DateTimePicker::make('starts_at'),
DateTimePicker::make('expires_at'),
Toggle::make('first_order_only'),
Toggle::make('premium_only'),
Toggle::make('is_active')->default(true),
```

### 6.9 SettingsPage (configuration globale)

```php
// app/Filament/Pages/Settings.php
class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static string $view = 'filament.pages.settings';
    protected static ?int $navigationSort = 99;

    public ?array $data = [];

    public function mount()
    {
        $this->form->fill([
            'company_name' => Setting::get('company_name', 'Boucherie Express'),
            'support_phone' => Setting::get('support_phone'),
            'support_email' => Setting::get('support_email'),
            'opening_hours' => Setting::get('opening_hours', '08:00-20:00'),
            'min_order_amount' => Setting::get('min_order_amount', 5000),
            'default_delivery_fee' => Setting::get('default_delivery_fee', 1500),
            'free_delivery_threshold' => Setting::get('free_delivery_threshold', 30000),
            'whatsapp_number' => Setting::get('whatsapp_number'),
            'maintenance_mode' => Setting::get('maintenance_mode', false),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make()->tabs([
                Tab::make('Général')->schema([
                    TextInput::make('company_name'),
                    TextInput::make('support_phone'),
                    TextInput::make('support_email')->email(),
                    TextInput::make('whatsapp_number'),
                    TextInput::make('opening_hours'),
                ]),
                Tab::make('Commandes')->schema([
                    TextInput::make('min_order_amount')->numeric()->suffix('FCFA'),
                    TextInput::make('default_delivery_fee')->numeric()->suffix('FCFA'),
                    TextInput::make('free_delivery_threshold')->numeric()->suffix('FCFA'),
                ]),
                Tab::make('Maintenance')->schema([
                    Toggle::make('maintenance_mode')->label('Mode maintenance (app indisponible)'),
                    Textarea::make('maintenance_message'),
                ]),
            ]),
        ])->statePath('data');
    }

    public function save()
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::set($key, $value);
        }
        Notification::make()->success()->title('Paramètres enregistrés')->send();
    }
}
```

Avec une table `settings` simple :
```php
Schema::create('settings', function (Blueprint $table) {
    $table->string('key')->primary();
    $table->json('value')->nullable();
    $table->timestamps();
});
```

### 6.10 Liste exhaustive des resources Filament à créer

| Resource | Statut | Onglets / sections |
|---|---|---|
| **ProductResource** | À créer | Infos, Prix/stock, Médias, Caractéristiques, Traçabilité, Tarification B2B |
| **CategoryResource** | À créer | Liste avec sort_order drag-drop, icône, sous-catégories |
| **OrderResource** | À adapter | Items (relation), Historique statuts, Paiements, Notifs envoyées |
| **CustomerResource** | À créer | Profil, Adresses, Commandes, Favoris, Stats, Notifs |
| **B2BAccountResource** | Sprint 6 | Compte, Tarifs négociés, Commandes récurrentes, Facturation |
| **CourierResource** | À créer | Profil, Zones, Statistiques, Position GPS |
| **SupplierResource** | Sprint 11 | Profil, Produits fournis, Stocks, Commandes d'achat |
| **PaymentResource** | À créer | Liste transactions, réconciliation, remboursements |
| **CouponResource** | À créer | Code, conditions, statistiques d'usage |
| **DeliveryZoneResource** | À créer | Communes, frais, seuils gratuité, livreurs assignés |
| **ProductBatchResource** | Sprint 10 | Lots, traçabilité abattage, péremption |
| **NotificationResource** | À créer | Historique, templates, broadcast |
| **RoleResource** | Existant Spatie | Permissions par module |

---

## 7. Sprint 6 — Volet B2B (restaurants, maquis, corporate)

### 7.1 Migrations

```php
Schema::create('b2b_accounts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('company_name');
    $table->string('business_type'); // restaurant, maquis, hotel, cantine, traiteur
    $table->string('rccm')->nullable(); // registre du commerce
    $table->string('billing_address');
    $table->string('contact_person');
    $table->string('contact_phone');
    $table->decimal('discount_rate', 5, 2)->default(0); // % de réduction global
    $table->integer('credit_limit')->default(0); // crédit autorisé pour facturation différée
    $table->integer('current_credit_used')->default(0);
    $table->enum('payment_terms', ['immediate', 'net_7', 'net_15', 'net_30'])->default('immediate');
    $table->enum('status', ['pending', 'active', 'suspended'])->default('pending');
    $table->timestamps();
});

Schema::create('b2b_custom_prices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('b2b_account_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->integer('price'); // prix négocié spécifique
    $table->integer('min_quantity')->default(1);
    $table->timestamps();
    $table->unique(['b2b_account_id', 'product_id']);
});

Schema::create('recurring_orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained();
    $table->string('name'); // "Livraison hebdo lundi"
    $table->enum('frequency', ['daily', 'weekly', 'biweekly', 'monthly']);
    $table->json('schedule'); // {"day_of_week": 1, "time": "08:00"}
    $table->json('items'); // [{product_id, quantity, option}]
    $table->foreignId('address_id')->constrained();
    $table->boolean('is_active')->default(true);
    $table->timestamp('next_run_at');
    $table->timestamps();
});

Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->string('number')->unique(); // INV-2026-001
    $table->foreignId('b2b_account_id')->constrained();
    $table->json('order_ids'); // commandes regroupées sur la facture
    $table->integer('subtotal');
    $table->integer('tax');
    $table->integer('total');
    $table->date('issue_date');
    $table->date('due_date');
    $table->enum('status', ['draft', 'sent', 'paid', 'overdue', 'cancelled'])->default('draft');
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();
});
```

### 7.2 Endpoints API B2B

```php
Route::middleware(['firebase.auth', 'b2b'])->prefix('v1/b2b')->group(function () {
    Route::get('account', [B2BController::class, 'show']);
    Route::get('catalog', [B2BController::class, 'catalog']); // avec prix négociés
    Route::get('invoices', [B2BController::class, 'invoices']);
    Route::get('invoices/{id}/pdf', [B2BController::class, 'downloadInvoice']);

    Route::apiResource('recurring-orders', RecurringOrderController::class);
    Route::post('recurring-orders/{id}/pause', [RecurringOrderController::class, 'pause']);
    Route::post('recurring-orders/{id}/resume', [RecurringOrderController::class, 'resume']);
});
```

### 7.3 Job de génération automatique des commandes récurrentes

```php
// app/Console/Commands/ProcessRecurringOrders.php
class ProcessRecurringOrders extends Command
{
    protected $signature = 'orders:process-recurring';

    public function handle(CheckoutService $checkout)
    {
        $due = RecurringOrder::where('is_active', true)
            ->where('next_run_at', '<=', now())
            ->get();

        foreach ($due as $recurring) {
            try {
                $checkout->createOrder(
                    $recurring->user,
                    $recurring->items,
                    'cash', // ou facture B2B
                    $recurring->address_id
                );
                $recurring->update(['next_run_at' => $this->computeNextRun($recurring)]);
            } catch (\Exception $e) {
                Log::error('Recurring order failed', ['id' => $recurring->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private function computeNextRun(RecurringOrder $r): Carbon
    {
        return match($r->frequency) {
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            'biweekly' => now()->addWeeks(2),
            'monthly' => now()->addMonth(),
        };
    }
}
```

Cron : `* * * * * cd /path && php artisan schedule:run` avec dans `Console/Kernel.php` :
```php
$schedule->command('orders:process-recurring')->everyFiveMinutes();
```

### 7.4 Génération de factures PDF

```bash
composer require barryvdh/laravel-dompdf
```

```php
// app/Services/InvoiceService.php
public function generate(B2BAccount $account, array $orderIds): Invoice
{
    $orders = Order::whereIn('id', $orderIds)->where('user_id', $account->user_id)->get();

    $invoice = Invoice::create([
        'number' => 'INV-' . now()->year . '-' . str_pad(Invoice::whereYear('created_at', now()->year)->count() + 1, 4, '0', STR_PAD_LEFT),
        'b2b_account_id' => $account->id,
        'order_ids' => $orderIds,
        'subtotal' => $orders->sum('total_price'),
        'tax' => 0, // TVA si applicable
        'total' => $orders->sum('total_amount'),
        'issue_date' => today(),
        'due_date' => $this->computeDueDate($account->payment_terms),
        'status' => 'sent',
    ]);

    $pdf = PDF::loadView('invoices.template', ['invoice' => $invoice, 'account' => $account, 'orders' => $orders]);
    $pdf->save(storage_path("app/invoices/{$invoice->number}.pdf"));

    return $invoice;
}
```

### 7.5 Filament — B2BAccountResource (extrait des onglets)

- **Identité** : raison sociale, type, RCCM, contact
- **Tarification** : taux de réduction global, prix négociés par produit (Repeater)
- **Crédit** : limite, utilisation actuelle, conditions de paiement
- **Commandes récurrentes** : liste avec statut
- **Factures** : liste avec statut, action "envoyer relance"
- **Statistiques** : volume mensuel, top produits, ancienneté

---

## 8. Sprint 7 — Programme fidélité Premium

### 8.1 Migrations

```php
Schema::create('subscription_plans', function (Blueprint $table) {
    $table->id();
    $table->string('name'); // "Premium Mensuel", "Premium Annuel"
    $table->integer('price'); // FCFA
    $table->enum('period', ['monthly', 'quarterly', 'yearly']);
    $table->json('benefits'); // {"free_delivery": true, "discount_pct": 5, ...}
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

Schema::create('subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('plan_id')->constrained('subscription_plans');
    $table->timestamp('started_at');
    $table->timestamp('expires_at');
    $table->enum('status', ['active', 'cancelled', 'expired', 'pending'])->default('pending');
    $table->boolean('auto_renew')->default(true);
    $table->string('payment_reference')->nullable();
    $table->timestamps();
});

Schema::create('loyalty_points', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->integer('points');
    $table->enum('type', ['earned', 'spent', 'expired', 'bonus']);
    $table->string('reason'); // "order_xyz", "signup_bonus", "premium_renewal"
    $table->morphs('source'); // Order ou Subscription
    $table->timestamps();
});
```

### 8.2 Logique des avantages

Adapter le `CheckoutService::resolvePrice()` (déjà fait section 3.3) :
- Premium : 5 % de réduction
- Premium : livraison gratuite si abonnement actif
- 10 points par tranche de 1000 FCFA dépensée
- Accès aux promos exclusives (champ `premium_only` sur les coupons)

### 8.3 Endpoints

```php
Route::middleware('firebase.auth')->group(function () {
    Route::get('subscription/plans', [SubscriptionController::class, 'plans']);
    Route::get('subscription/current', [SubscriptionController::class, 'current']);
    Route::post('subscription/subscribe', [SubscriptionController::class, 'subscribe']);
    Route::post('subscription/cancel', [SubscriptionController::class, 'cancel']);

    Route::get('loyalty/balance', [LoyaltyController::class, 'balance']);
    Route::get('loyalty/history', [LoyaltyController::class, 'history']);
    Route::post('loyalty/redeem', [LoyaltyController::class, 'redeem']); // échanger contre coupon
});
```

### 8.4 Job de vérification quotidienne des abonnements

```php
class CheckSubscriptions extends Command
{
    protected $signature = 'subscriptions:check';

    public function handle()
    {
        // Désactiver les abonnements expirés
        $expired = Subscription::where('status', 'active')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $sub) {
            if ($sub->auto_renew) {
                // Tenter le renouvellement automatique via CinetPay
                $this->renewSubscription($sub);
            } else {
                $sub->update(['status' => 'expired']);
                $sub->user->update(['is_premium' => false]);
            }
        }

        // Notifier les abonnements qui expirent dans 3 jours
        $expiringSoon = Subscription::where('status', 'active')
            ->whereBetween('expires_at', [now()->addDays(2), now()->addDays(4)])
            ->get();

        foreach ($expiringSoon as $sub) {
            // FCM : "Votre abonnement Premium expire bientôt"
        }
    }
}
```

---

## 9. Sprint 8 — Géolocalisation livreurs temps réel

### 9.1 Migrations

```php
Schema::table('couriers', function (Blueprint $table) {
    $table->decimal('current_lat', 10, 7)->nullable();
    $table->decimal('current_lng', 10, 7)->nullable();
    $table->decimal('current_heading', 5, 2)->nullable(); // direction en degrés
    $table->decimal('current_speed', 5, 2)->nullable();
    $table->timestamp('location_updated_at')->nullable();
});

Schema::create('courier_location_history', function (Blueprint $table) {
    $table->id();
    $table->foreignId('courier_id')->constrained();
    $table->foreignId('order_id')->nullable();
    $table->decimal('lat', 10, 7);
    $table->decimal('lng', 10, 7);
    $table->timestamp('recorded_at');
    $table->index(['courier_id', 'recorded_at']);
});
```

### 9.2 Endpoint app livreur (à terme app séparée)

```php
Route::middleware(['firebase.auth', 'courier'])->prefix('v1/courier')->group(function () {
    Route::post('location', [CourierController::class, 'updateLocation']);
    Route::get('orders/active', [CourierController::class, 'activeOrders']);
    Route::post('orders/{id}/start', [CourierController::class, 'startDelivery']);
    Route::post('orders/{id}/complete', [CourierController::class, 'completeDelivery']);
});
```

```php
public function updateLocation(Request $request)
{
    $request->validate([
        'lat' => 'required|numeric|between:-90,90',
        'lng' => 'required|numeric|between:-180,180',
        'heading' => 'nullable|numeric',
        'speed' => 'nullable|numeric',
    ]);

    $courier = $request->user()->courier;
    $courier->update([
        'current_lat' => $request->input('lat'),
        'current_lng' => $request->input('lng'),
        'current_heading' => $request->input('heading'),
        'current_speed' => $request->input('speed'),
        'location_updated_at' => now(),
    ]);

    // Historique (sample 1 sur 10 pour limiter le volume)
    if (rand(1, 10) === 1) {
        $activeOrder = Order::where('courier_id', $courier->id)->where('status', 'delivering')->first();
        CourierLocationHistory::create([
            'courier_id' => $courier->id,
            'order_id' => $activeOrder?->id,
            'lat' => $request->input('lat'),
            'lng' => $request->input('lng'),
            'recorded_at' => now(),
        ]);
    }

    return $this->ok(['updated' => true]);
}
```

### 9.3 Endpoint client pour suivre son livreur

```php
Route::get('orders/{id}/courier-location', [OrderController::class, 'courierLocation'])
    ->middleware('firebase.auth');
```

```php
public function courierLocation(Request $request, $id)
{
    $order = Order::where('user_id', $request->user()->id)->findOrFail($id);
    if (!$order->courier_id || !in_array($order->status, ['delivering'])) {
        return $this->fail('Pas de livreur en cours', 404);
    }

    $courier = $order->courier;
    return $this->ok([
        'lat' => (float) $courier->current_lat,
        'lng' => (float) $courier->current_lng,
        'heading' => (float) $courier->current_heading,
        'lastUpdate' => $courier->location_updated_at?->toIso8601String(),
    ]);
}
```

L'app Flutter polle cette URL toutes les 5-10 secondes pendant la livraison. **Alternative plus propre** : Laravel Reverb (WebSockets natif depuis Laravel 11) pour push en temps réel — à envisager si tu veux pousser la qualité.

### 9.4 Carte temps réel dans Filament (vue Blade)

```blade
{{-- resources/views/filament/pages/courier-map.blade.php --}}
<x-filament::page>
    <div id="courier-map" style="height: 600px; border-radius: 0.5rem;"></div>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        const map = L.map('courier-map').setView([5.345, -4.024], 12); // Abidjan
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        const markers = {};

        async function refreshCouriers() {
            const res = await fetch('/admin/api/couriers-locations');
            const data = await res.json();

            data.forEach(c => {
                if (markers[c.id]) {
                    markers[c.id].setLatLng([c.lat, c.lng]);
                } else {
                    markers[c.id] = L.marker([c.lat, c.lng])
                        .bindPopup(`<b>${c.name}</b><br>${c.order ? 'Cmd #' + c.order.id : 'Disponible'}`)
                        .addTo(map);
                }
            });
        }

        refreshCouriers();
        setInterval(refreshCouriers, 5000);
    </script>
</x-filament::page>
```

---

## 10. Sprint 9 — Optimisation des tournées

### 10.1 Migration zones

```php
Schema::create('delivery_zones', function (Blueprint $table) {
    $table->id();
    $table->string('name'); // "Cocody Centre"
    $table->string('city'); // "Abidjan"
    $table->json('polygon'); // GeoJSON polygon de la zone
    $table->integer('fee')->default(1500);
    $table->integer('free_delivery_threshold')->default(30000);
    $table->integer('estimated_delivery_minutes')->default(60);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

Schema::create('courier_zone_assignments', function (Blueprint $table) {
    $table->foreignId('courier_id')->constrained();
    $table->foreignId('zone_id')->constrained('delivery_zones');
    $table->primary(['courier_id', 'zone_id']);
});
```

### 10.2 Service d'assignation automatique

```php
// app/Services/DeliveryDispatchService.php
class DeliveryDispatchService
{
    public function autoAssignCourier(Order $order): ?Courier
    {
        // 1. Trouver la zone de livraison
        $zone = $this->findZoneByAddress($order->delivery_address);
        if (!$zone) return null;

        // 2. Livreurs assignés à cette zone, actifs et disponibles
        $candidates = Courier::where('is_active', true)
            ->where('is_available', true)
            ->whereHas('zones', fn($q) => $q->where('zone_id', $zone->id))
            ->withCount(['orders as active_count' => fn($q) => $q->whereIn('status', ['delivering', 'preparing'])])
            ->orderBy('active_count') // moins chargé d'abord
            ->get();

        if ($candidates->isEmpty()) return null;

        // 3. Si plusieurs candidats à charge égale, prendre le plus proche
        $courier = $candidates->first();
        $order->update(['courier_id' => $courier->id]);

        // 4. Notifier le livreur
        if ($courier->fcm_token) {
            app(FirebaseMessagingService::class)->sendToToken(
                $courier->fcm_token,
                'Nouvelle livraison',
                "Commande #{$order->id} - {$order->delivery_address}",
                ['type' => 'new_delivery', 'order_id' => $order->id]
            );
        }

        return $courier;
    }

    private function findZoneByAddress(string $address): ?DeliveryZone
    {
        // V1 : matching par mots-clés sur le nom de la zone (basique mais marche)
        return DeliveryZone::where('is_active', true)
            ->get()
            ->first(fn($z) => str_contains(strtolower($address), strtolower($z->name)));

        // V2 (plus tard) : géocoder l'adresse en lat/lng et tester l'inclusion dans le polygon
    }
}
```

### 10.3 Optimisation multi-stops (V2)

Pour grouper plusieurs livraisons dans une même tournée du livreur, utiliser un algorithme nearest-neighbor simple ou intégrer Google Routes API. Hors-scope MVP, à prévoir année 2.

---

## 11. Sprint 10 — Traçabilité avancée

### 11.1 Migration product_batches

```php
Schema::create('product_batches', function (Blueprint $table) {
    $table->id();
    $table->string('lot_number')->unique();
    $table->foreignId('product_id')->constrained();
    $table->foreignId('supplier_id')->nullable()->constrained();
    $table->date('slaughter_date');
    $table->date('reception_date');
    $table->date('expiration_date');
    $table->integer('initial_quantity'); // en kg
    $table->integer('current_quantity');
    $table->string('storage_location')->nullable(); // "Chambre froide A1"
    $table->json('certifications')->nullable(); // ["halal", "bio"]
    $table->json('quality_checks')->nullable(); // résultats des contrôles
    $table->enum('status', ['received', 'in_storage', 'in_use', 'depleted', 'recalled'])->default('received');
    $table->timestamps();
});

Schema::create('temperature_logs', function (Blueprint $table) {
    $table->id();
    $table->string('storage_location');
    $table->decimal('temperature', 4, 2); // -25.50
    $table->timestamp('recorded_at');
    $table->boolean('alert_triggered')->default(false);
    $table->index(['storage_location', 'recorded_at']);
});
```

### 11.2 Fiche produit étendue côté Flutter

L'API doit retourner pour chaque produit (quand un lot est attribué) :
- `slaughter_date`
- `origin_farm`
- `lot_number`
- `expiration_date`
- `certifications` (halal, bio)

C'est l'argument différenciant n°1 du pitch. Ne le néglige pas.

### 11.3 Filament — Resource ProductBatch

Tabs : **Réception** (date, fournisseur, quantité, contrôle qualité) → **Stockage** (location, température) → **Utilisation** (commandes liées) → **Certifications** (PDF uploadés).

### 11.4 Surveillance température (intégration capteurs IoT)

Si plus tard tu équipes ta chambre froide de capteurs IoT (ESP32 + DS18B20, ~5K FCFA), ils peuvent push la température toutes les 5 min vers `POST /api/temperature-logs` avec une clé d'API. Trigger d'alerte FCM admin si > 4°C.

---

## 12. Sprint 11 — Multi-fournisseurs

### 12.1 Migration suppliers

```php
Schema::create('suppliers', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->enum('type', ['farm', 'slaughterhouse', 'wholesaler']);
    $table->string('contact_name');
    $table->string('phone');
    $table->string('email')->nullable();
    $table->string('address');
    $table->string('city');
    $table->json('certifications')->nullable(); // halal, bio, ISO 22000
    $table->decimal('rating', 2, 1)->default(5.0);
    $table->json('specialties'); // ["beef", "lamb"]
    $table->enum('status', ['active', 'pending', 'suspended'])->default('pending');
    $table->timestamps();
});

Schema::create('supplier_products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->integer('purchase_price'); // prix d'achat
    $table->integer('current_stock')->default(0);
    $table->integer('moq')->default(1); // minimum order quantity
    $table->integer('lead_time_days')->default(1);
    $table->boolean('is_preferred')->default(false);
    $table->unique(['supplier_id', 'product_id']);
});

Schema::create('purchase_orders', function (Blueprint $table) {
    $table->id();
    $table->string('reference')->unique();
    $table->foreignId('supplier_id')->constrained();
    $table->json('items'); // [{product_id, quantity, unit_price}]
    $table->integer('total');
    $table->date('order_date');
    $table->date('expected_delivery');
    $table->date('actual_delivery')->nullable();
    $table->enum('status', ['draft', 'sent', 'confirmed', 'received', 'cancelled']);
    $table->timestamps();
});
```

### 12.2 Logique d'achat

À chaque commande client, le `Product.stock` baisse. Un job quotidien `inventory:check` vérifie quels produits passent sous leur seuil de réappro et propose à l'admin une suggestion d'achat (PurchaseOrder en statut `draft`).

### 12.3 Filament — SupplierResource (onglets)

- **Identité** : nom, type, certifications (PDF), notation
- **Catalogue** : produits fournis avec prix d'achat (Repeater)
- **Stocks** : volume disponible chez le fournisseur
- **Bons de commande** : historique avec statut
- **Performance** : taux de respect des délais, qualité moyenne

---

## 13. Sprint 12 — Analytics, CRM & marketing

### 13.1 Page d'analytics avancée Filament

```php
// app/Filament/Pages/Analytics.php
class Analytics extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static string $view = 'filament.pages.analytics';

    public function getCohortData(): array { /* rétention par mois d'inscription */ }
    public function getLtvBySegment(): array { /* B2C vs B2B vs Premium */ }
    public function getFunnelData(): array { /* installation → 1ère commande → 3ème commande */ }
    public function getGeographicData(): array { /* CA par commune */ }
    public function getPaymentMethodSplit(): array { /* OM/MTN/Wave/cash */ }
}
```

Métriques clés à afficher :
- Cohorte de rétention 30/60/90 jours par mois d'acquisition
- LTV moyen par segment
- CAC moyen et tendance
- Funnel : installations → signups → 1re commande → 3 commandes
- Heatmap horaire des commandes (savoir quand renforcer livreurs)
- Carte de chaleur par commune (avec Leaflet)
- Top 20 clients (CA total, fréquence, dernier achat)

### 13.2 Module CRM — campagnes & segments

```php
Schema::create('customer_segments', function (Blueprint $table) {
    $table->id();
    $table->string('name'); // "Inactifs > 30j", "VIP", "Nouveaux"
    $table->json('criteria'); // {"min_orders": 3, "last_order_days_ago": 30}
    $table->integer('cached_count')->default(0);
    $table->timestamp('last_refreshed_at')->nullable();
    $table->timestamps();
});

Schema::create('campaigns', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->enum('channel', ['push', 'sms', 'whatsapp']);
    $table->foreignId('segment_id')->constrained('customer_segments');
    $table->string('title');
    $table->text('body');
    $table->json('data')->nullable(); // payload (deep link, coupon)
    $table->timestamp('scheduled_at')->nullable();
    $table->timestamp('sent_at')->nullable();
    $table->integer('targeted_count')->default(0);
    $table->integer('delivered_count')->default(0);
    $table->integer('opened_count')->default(0);
    $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'cancelled']);
    $table->timestamps();
});
```

### 13.3 Filament — Resource Campaign

- Form : sélection du segment (preview du nombre de cibles), composition du message (title + body + data), programmation, canal
- Action : "Tester sur moi" (envoie à l'admin uniquement)
- Stats : impressions, clics, conversions (commandes générées dans les 24h)

### 13.4 Export CRM

Action Filament `Export clients` qui génère un CSV avec : id, nom, téléphone, email, total dépensé, fréquence, dernière commande, segment. Réutilisable pour campagnes externes (relances WhatsApp manuelles, par ex.).

---

## 14. Annexes

### 14.1 Sécurité globale — checklist

- ✅ HTTPS partout, certificat Let's Encrypt en prod
- ✅ Validation stricte via FormRequest (jamais `$request->all()` dans `update()`)
- ✅ `where('user_id', $request->user()->id)` systématique avant lecture/modification de ressource utilisateur
- ✅ Rate limiting sur routes sensibles : `throttle:30,1` sur checkout, `throttle:5,1` sur payment/initialize
- ✅ Webhook PSP : vérification HMAC obligatoire
- ✅ Headers CSP en prod
- ✅ Disable debug mode en prod (`APP_DEBUG=false`)
- ✅ Secrets dans `.env` jamais commités
- ✅ Logs : ne JAMAIS logger les tokens Firebase complets ou les clés CinetPay
- ✅ Backups DB chiffrés stockés hors du serveur principal

### 14.2 Performance — patterns à appliquer

- **Eager loading** : `Order::with(['items', 'user', 'courier'])->get()` partout
- **Cache Redis** : catégories `Cache::remember('categories.all', 3600, fn() => Category::all())`
- **Index DB** : sur `user_id`, `status`, `payment_status`, `ordered_at`, `category_id`, `firebase_uid`
- **Pagination** : `->paginate(20)` sur orders, products, customers en admin
- **Queue** : tout envoi FCM, génération PDF, email, dispatch livreur passe en queue
- **Image optimization** : redimensionner les uploads avec Intervention Image, servir en WebP

### 14.3 Tests — minimum vital

```bash
composer require --dev pestphp/pest pestphp/pest-plugin-laravel
php artisan pest:install
```

Tests prioritaires (Pest) :
- `CheckoutServiceTest` : transaction, lock stock, calcul total, frais livraison
- `FirebaseAuthMiddlewareTest` : token valide / invalide / expiré / absent
- `OrderTrackingTest` : labels exacts (mots-clés Flutter), états progressifs
- `CinetPayWebhookTest` : signature HMAC, mise à jour ordre, dispatch notif
- `AddressControllerTest` : chaque mutation retourne la liste complète

Pas besoin de couverture 100 %. Couvre les flows business critiques, c'est suffisant.

### 14.4 Déploiement — script de déploiement minimaliste

`deploy.sh` sur le serveur :
```bash
#!/bin/bash
set -e
cd /var/www/boucherie

php artisan down --secret="deploy-secret"
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan queue:restart
sudo systemctl restart php8.2-fpm
php artisan up

echo "Déploiement OK"
```

### 14.5 Suggestions d'ordre d'exécution sur 12 semaines

| Semaine | Sprint | Livrables |
|---|---|---|
| 1 | Sprint 1 bis | Firebase Auth + format réponse + ProfileController |
| 2 | Sprint 2 | Favorites + Checkout + Tracking + Address fix |
| 3 | Sprint 3 | FCM + Avatar + Seeders + Queue Redis |
| 4 | Sprint 4 | CinetPay sandbox + webhook HMAC |
| **5-6** | **Sprint 5** | **Filament : Dashboard + tous les Resources core** |
| 7 | Sprint 6 | B2B accounts + commandes récurrentes |
| 8 | Sprint 7 | Premium + loyalty points |
| 9 | Sprint 8 | Géoloc temps réel livreurs |
| 10 | Sprint 9 | Zones + dispatch automatique |
| 11 | Sprint 10 | Lots + traçabilité + capteurs température |
| 12 | Sprint 11 + 12 | Suppliers + Analytics + CRM campaigns |

### 14.6 Ce que je te recommande de NE PAS faire

- **N'attaque pas Filament avant que les sprints 1-4 soient stables.** L'admin a besoin d'avoir des données réelles à manipuler.
- **Ne fais pas la géoloc temps réel avant d'avoir l'app livreur.** Sans app livreur qui push la position, le code de réception ne sert à rien.
- **N'implémente pas le multi-fournisseurs avant d'avoir 2-3 fournisseurs réels.** Sinon tu sur-modélises pour rien.
- **Ne te lance pas dans Reverb/WebSockets avant d'avoir validé que le polling 5-10s ne suffit pas.** Le simple est ton ami.

---

## Récapitulatif décisionnel

1. **Migrer Sanctum → Firebase pour l'API mobile**, garder Sanctum pour Filament
2. **Adapter tous les controllers existants au format `{success, data}`** + camelCase dans les Resources
3. **Filament EN PRIORITÉ après les sprints 1-4 stables** (semaines 5-6) — c'est le moment où tu maximises ta capacité à piloter le business
4. **Construire tous les modules métier (B2B, Premium, géoloc, traçabilité)** comme des couches additives, pas comme des refontes
5. **Tester le checkout et le webhook CinetPay** avec des tests automatisés — ce sont les seuls flux où un bug coûte de l'argent réel

---

*Document vivant. Mets à jour au fur et à mesure de l'exécution.*
