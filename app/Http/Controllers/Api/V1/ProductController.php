<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Product::with('category')->where('is_active', true);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $products = $query->paginate($request->per_page ?? 20);

        return $this->ok(ProductResource::collection($products)->response()->getData(true));
    }

    public function show(int $id)
    {
        $product = Product::with('category')->findOrFail($id);
        return $this->ok(new ProductResource($product));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'stock'       => 'integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'image'       => 'nullable|string',
        ]);

        $product = Product::create($validated);
        return $this->ok(new ProductResource($product), 201);
    }

    public function update(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'sometimes|numeric|min:0',
            'stock'       => 'sometimes|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'image'       => 'nullable|string',
            'is_active'   => 'sometimes|boolean',
        ]);
        $product->update($validated);
        return $this->ok(new ProductResource($product));
    }

    public function destroy(int $id)
    {
        $product = Product::findOrFail($id);
        $product->delete();
        return $this->ok(['message' => 'Produit supprimé']);
    }
}
