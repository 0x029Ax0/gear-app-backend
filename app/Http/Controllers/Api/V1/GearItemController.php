<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGearItemRequest;
use App\Http\Requests\UpdateGearItemRequest;
use App\Http\Resources\GearItemResource;
use App\Models\Category;
use App\Models\GearItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GearItemController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = GearItem::query()->where('user_id', $request->user()->id)->with('category');
        $this->applyFilters($query, $request);

        $summary = [
            'quantity' => (int) (clone $query)->sum('quantity'),
            'weight_grams' => (int) (clone $query)->selectRaw('COALESCE(SUM(weight_grams * quantity), 0) as total')->value('total'),
            'price_minor' => (int) (clone $query)->selectRaw('COALESCE(SUM(price_minor * quantity), 0) as total')->value('total'),
        ];
        $items = $query->paginate(min($request->integer('per_page', 20), 100))->withQueryString();

        return GearItemResource::collection($items)->additional(['meta' => ['summary' => $summary]]);
    }

    public function store(StoreGearItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->ensureCategoryVisible($request, $data['category_id'] ?? null);
        $data['user_id'] = $request->user()->id;
        $item = GearItem::create($data)->load('category');

        return response()->json(['data' => new GearItemResource($item)], 201);
    }

    public function show(Request $request, GearItem $gearItem): GearItemResource
    {
        Gate::authorize('view', $gearItem);

        return new GearItemResource($gearItem->load('category'));
    }

    public function update(UpdateGearItemRequest $request, GearItem $gearItem): GearItemResource
    {
        Gate::authorize('update', $gearItem);
        $data = $request->validated();
        if (($data['is_owned'] ?? $gearItem->is_owned) && ($data['is_ordered'] ?? $gearItem->is_ordered)) {
            throw ValidationException::withMessages(['is_owned' => 'An item cannot be both owned and ordered.']);
        }
        $this->ensureCategoryVisible($request, $data['category_id'] ?? $gearItem->category_id);
        $gearItem->update($data);

        return new GearItemResource($gearItem->refresh()->load('category'));
    }

    public function destroy(Request $request, GearItem $gearItem): JsonResponse
    {
        Gate::authorize('delete', $gearItem);
        $gearItem->delete();

        return response()->json([], 204);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }
        foreach (['is_owned', 'is_ordered'] as $flag) {
            if ($request->has($flag)) {
                $query->where($flag, $request->boolean($flag));
            }
        }
        $allowedSorts = ['name', 'quantity', 'weight_grams', 'price_minor', 'created_at'];
        $sort = (string) $request->input('sort', 'name');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $sort = ltrim($sort, '-');
        $query->orderBy(in_array($sort, $allowedSorts, true) ? $sort : 'name', $direction);
    }

    private function ensureCategoryVisible(Request $request, ?int $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }
        $visible = Category::query()->whereKey($categoryId)->where(function ($query) use ($request): void {
            $query->where('is_system', true)->orWhere('user_id', $request->user()->id);
        })->exists();
        if (! $visible) {
            throw ValidationException::withMessages(['category_id' => 'The selected category is invalid.']);
        }
    }
}
