<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Favorite;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $products = Product::with('category')
            ->whereIn('id', function ($q) use ($request) {
                $q->select('product_id')
                  ->from('favorites')
                  ->where('user_id', $request->user()->id);
            })
            ->where('is_active', true)
            ->get();

        return $this->ok(ProductResource::collection($products));
    }

    public function add(Request $request, int $productId)
    {
        Favorite::firstOrCreate([
            'user_id'    => $request->user()->id,
            'product_id' => $productId,
        ]);
        return $this->ok(['added' => true]);
    }

    public function remove(Request $request, int $productId)
    {
        Favorite::where('user_id', $request->user()->id)
            ->where('product_id', $productId)
            ->delete();
        return $this->ok(['removed' => true]);
    }
}
