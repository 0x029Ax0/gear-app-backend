<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductImportRequest;
use App\Http\Resources\ProductImportResource;
use App\Jobs\ImportProductFromUrl;
use App\Models\Category;
use App\Models\ProductImport;
use App\Services\ProductImports\ProductImportException;
use App\Services\ProductImports\SafeUrlValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class ProductImportController extends Controller
{
    public function __construct(private readonly SafeUrlValidator $validator) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ProductImportResource::collection(ProductImport::query()->where('user_id', $request->user()->id)->latest()->paginate(20));
    }

    public function store(StoreProductImportRequest $request): JsonResponse
    {
        $key = 'product-import:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, (int) config('product_imports.rate_limit_per_minute'))) {
            throw new TooManyRequestsHttpException(null, 'Too many product imports.');
        }
        RateLimiter::hit($key, 60);
        $data = $request->validated();
        try {
            $this->validator->validate($data['url']);
        } catch (ProductImportException $exception) {
            throw ValidationException::withMessages(['url' => $exception->getMessage()]);
        }
        $pending = ProductImport::query()->where('user_id', $request->user()->id)->whereIn('status', [ProductImport::PENDING, ProductImport::PROCESSING])->count();
        if ($pending >= (int) config('product_imports.max_pending_per_user')) {
            throw ValidationException::withMessages(['url' => 'Too many product imports are already pending.']);
        }
        if (($data['category_id'] ?? null) !== null && ! Category::query()->whereKey($data['category_id'])->where(fn ($query) => $query->where('is_system', true)->orWhere('user_id', $request->user()->id))->exists()) {
            throw ValidationException::withMessages(['category_id' => 'The selected category is invalid.']);
        }
        if (($data['in_possession'] ?? false) && ($data['ordered'] ?? false)) {
            throw ValidationException::withMessages(['in_possession' => 'An item cannot be both in possession and ordered.']);
        }
        $import = ProductImport::create([
            'user_id' => $request->user()->id,
            'category_id' => $data['category_id'] ?? null,
            'source_url' => $data['url'],
            'status' => ProductImport::PENDING,
            'quantity' => $data['quantity'] ?? 1,
            'in_possession' => $data['in_possession'] ?? false,
            'ordered' => $data['ordered'] ?? false,
            'expires_at' => now()->addHours((int) config('product_imports.ttl_hours')),
        ]);
        ImportProductFromUrl::dispatch($import->id);

        return response()->json(['data' => new ProductImportResource($import)], 202);
    }

    public function show(Request $request, ProductImport $productImport): ProductImportResource
    {
        abort_unless($productImport->user_id === $request->user()->id, 404);

        return new ProductImportResource($productImport);
    }

    public function destroy(Request $request, ProductImport $productImport): JsonResponse
    {
        abort_unless($productImport->user_id === $request->user()->id, 404);
        $productImport->delete();

        return response()->json([], 204);
    }
}
