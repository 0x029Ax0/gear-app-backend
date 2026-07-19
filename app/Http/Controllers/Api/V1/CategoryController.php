<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->where('is_system', true)
            ->orWhere('user_id', $request->user()->id)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => CategoryResource::collection($categories)]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $name = trim(preg_replace('/\s+/u', ' ', $request->validated('name')));
        $normalizedName = Category::normalizeName($name);
        $this->ensureNameAvailable($request->user()->id, $normalizedName);

        try {
            $category = Category::create([
                'user_id' => $request->user()->id,
                'name' => $name,
                'normalized_name' => $normalizedName,
                'is_system' => false,
            ]);
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'categories')) {
                throw $exception;
            }

            throw ValidationException::withMessages(['name' => 'This category name is already in use.']);
        }

        return response()->json(['data' => new CategoryResource($category)], 201);
    }

    public function show(Request $request, Category $category): CategoryResource
    {
        $this->visibleOrFail($request, $category);

        return new CategoryResource($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $this->visibleOrFail($request, $category);
        Gate::authorize('update', $category);

        $name = trim(preg_replace('/\s+/u', ' ', $request->validated('name')));
        $normalizedName = Category::normalizeName($name);
        $this->ensureNameAvailable($request->user()->id, $normalizedName, $category->id);
        $category->update(['name' => $name, 'normalized_name' => $normalizedName]);

        return new CategoryResource($category->refresh());
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        $this->visibleOrFail($request, $category);
        Gate::authorize('delete', $category);

        if ($this->isInUse($category)) {
            return response()->json([
                'message' => 'This category cannot be deleted because it is in use.',
                'code' => 'CATEGORY_IN_USE',
            ], 409);
        }

        $category->delete();

        return response()->json([], 204);
    }

    private function visibleOrFail(Request $request, Category $category): void
    {
        abort_unless($category->is_system || $category->user_id === $request->user()->id, 404);
    }

    private function ensureNameAvailable(int $userId, string $normalizedName, ?int $ignoreId = null): void
    {
        $query = Category::query()
            ->where('normalized_name', $normalizedName)
            ->where(function ($query) use ($userId): void {
                $query->where('is_system', true)->orWhere('user_id', $userId);
            });

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages(['name' => 'This category name is already in use.']);
        }
    }

    private function isInUse(Category $category): bool
    {
        foreach (['gear_items', 'items'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $query = DB::table($table)->where('category_id', $category->id);
            if (Schema::hasColumn($table, 'deleted_at')) {
                $query->whereNull('deleted_at');
            }
            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }
}
