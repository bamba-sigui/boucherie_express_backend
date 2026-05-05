<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use ApiResponse;

    private function format(Category $category): array
    {
        return [
            'id'          => $category->id,
            'name'        => $category->name,
            'description' => $category->description,
            'icon'        => $category->icon,
            'image'       => $category->image,
            'sortOrder'   => (int) ($category->sort_order ?? 0),
        ];
    }

    public function index()
    {
        $categories = Category::orderBy('sort_order')->orderBy('name')->get();
        return $this->ok($categories->map(fn ($c) => $this->format($c))->values());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'image'       => 'nullable|string',
            'icon'        => 'nullable|string',
            'sort_order'  => 'nullable|integer',
        ]);
        $category = Category::create($validated);
        return $this->ok($this->format($category), 201);
    }

    public function update(Request $request, int $id)
    {
        $category = Category::findOrFail($id);
        $validated = $request->validate([
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'image'       => 'nullable|string',
            'icon'        => 'nullable|string',
            'sort_order'  => 'nullable|integer',
        ]);
        $category->update($validated);
        return $this->ok($this->format($category));
    }

    public function destroy(int $id)
    {
        $category = Category::findOrFail($id);
        $category->delete();
        return $this->ok(['message' => 'Catégorie supprimée']);
    }
}
