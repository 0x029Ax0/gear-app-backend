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

        $summaryRows = (clone $query)->get(['quantity', 'weight_grams', 'price_minor', 'currency_code']);
        $currencies = $summaryRows->pluck('currency_code')->filter()->unique()->values();
        $summary = [
            'item_rows' => $summaryRows->count(),
            'total_quantity' => (int) $summaryRows->sum('quantity'),
            'total_weight_grams' => (int) $summaryRows->sum(fn (GearItem $item): int => $item->weight_grams * $item->quantity),
        ];
        if ($currencies->count() <= 1) {
            $summary['currency_code'] = $currencies->first();
            $summary['total_value_minor'] = (int) $summaryRows->sum(fn (GearItem $item): int => (int) $item->price_minor * $item->quantity);
        } else {
            $summary['values_by_currency'] = $summaryRows->groupBy('currency_code')->map(fn ($items): int => (int) $items->sum(fn (GearItem $item): int => (int) $item->price_minor * $item->quantity));
        }
        $items = $query->paginate(min($request->integer('per_page', 20), 100))->withQueryString();

        return GearItemResource::collection($items)->additional(['meta' => ['summary' => $summary]]);
    }

    public function store(StoreGearItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->ensureCategoryVisible($request, $data['category_id'] ?? null);
        $this->ensureValidState($data['in_possession'], $data['ordered']);
        $data['user_id'] = $request->user()->id;
        if ($data['price_minor'] ?? null) {
            $data['currency_code'] = strtoupper($data['currency_code']);
        }
        $item = GearItem::create($data)->load('category');

        return response()->json(['data' => new GearItemResource($item)], 201);
    }

    public function show(Request $request, GearItem $gearItem): GearItemResource
    {
        $this->ensureOwned($request, $gearItem);
        Gate::authorize('view', $gearItem);

        return new GearItemResource($gearItem->load('category'));
    }

    public function update(UpdateGearItemRequest $request, GearItem $gearItem): GearItemResource
    {
        $this->ensureOwned($request, $gearItem);
        Gate::authorize('update', $gearItem);
        $data = $request->validated();
        $inPossession = $data['in_possession'] ?? $gearItem->in_possession;
        $ordered = $data['ordered'] ?? $gearItem->ordered;
        $this->ensureValidState($inPossession, $ordered);
        $this->ensureCategoryVisible($request, $data['category_id'] ?? $gearItem->category_id);
        $priceMinor = array_key_exists('price_minor', $data) ? $data['price_minor'] : $gearItem->price_minor;
        $data['currency_code'] = $priceMinor === null ? null : strtoupper($data['currency_code'] ?? $gearItem->currency_code);
        $gearItem->update($data);

        return new GearItemResource($gearItem->refresh()->load('category'));
    }

    public function destroy(Request $request, GearItem $gearItem): JsonResponse
    {
        $this->ensureOwned($request, $gearItem);
        Gate::authorize('delete', $gearItem);
        $gearItem->delete();

        return response()->json([], 204);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));
            $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }
        foreach (['in_possession', 'ordered'] as $flag) {
            if ($request->has($flag)) {
                $query->where($flag, $request->boolean($flag));
            }
        }
        $allowedSorts = ['name', 'quantity', 'weight_grams', 'price_minor', 'created_at', 'updated_at'];
        $sort = (string) $request->input('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $sort = ltrim($sort, '-');
        if (! in_array($sort, $allowedSorts, true)) {
            throw ValidationException::withMessages(['sort' => 'The selected sort is invalid.']);
        }
        $query->orderBy($sort, $direction);
    }

    private function ensureValidState(bool $inPossession, bool $ordered): void
    {
        if ($inPossession && $ordered) {
            throw ValidationException::withMessages(['in_possession' => 'An item cannot be both in possession and ordered.']);
        }
    }

    private function ensureOwned(Request $request, GearItem $gearItem): void
    {
        abort_unless($gearItem->user_id === $request->user()->id, 404);
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
