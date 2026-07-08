<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'ok' => true,
            'data' => $categories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('categories')],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);

        $category = Category::create($validated);

        AuditLogger::logModelChange(AuditAction::CATEGORY_CREATED, $category);

        return response()->json([
            'ok' => true,
            'data' => $category,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::query()->findOrFail($id);

        $this->authorize('update', $category);

        $old = $category->toArray();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('categories')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ]);

        $category->update($validated);

        AuditLogger::log(
            AuditAction::CATEGORY_UPDATED,
            'Category',
            $category->id,
            $old,
            $category->toArray(),
            auth()->user()?->id,
        );

        return response()->json([
            'ok' => true,
            'data' => $category,
        ]);
    }
}
