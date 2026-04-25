<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\StoreProductVariantRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search')->toString());
        $origin = trim((string) $request->string('origin')->toString());
        $color = trim((string) $request->string('color')->toString());

        $products = Product::query()
            ->with(['variants'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($origin !== '', function ($query) use ($origin): void {
                $query->whereHas('variants', function ($variantQuery) use ($origin): void {
                    $variantQuery->where('origin', $origin);
                });
            })
            ->when($color !== '', function ($query) use ($color): void {
                $query->whereHas('variants', function ($variantQuery) use ($color): void {
                    $variantQuery->where('color', $color);
                });
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(function (Product $product): array {
                $variants = $product->variants;

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'base_unit' => $product->base_unit,
                    'variant_count' => $variants->count(),
                    'origins' => $variants->pluck('origin')->filter()->unique()->values()->all(),
                    'colors' => $variants->pluck('color')->filter()->unique()->values()->all(),
                    'skus' => $variants->pluck('sku')->filter()->unique()->values()->all(),
                    'thicknesses' => $variants->pluck('thickness')->filter(static fn ($value): bool => $value !== null)->map(static fn ($value): string => (string) $value)->unique()->values()->all(),
                    'sizes' => $variants->pluck('size')->filter()->unique()->values()->all(),
                    'created_at' => $product->created_at,
                ];
            });

        return Inertia::render('products/index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'origin' => $origin,
                'color' => $color,
            ],
            'origins' => ProductVariant::query()
                ->whereNotNull('origin')
                ->distinct()
                ->orderBy('origin')
                ->pluck('origin')
                ->values()
                ->all(),
            'colors' => ProductVariant::query()
                ->whereNotNull('color')
                ->distinct()
                ->orderBy('color')
                ->pluck('color')
                ->values()
                ->all(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        Product::query()->create($request->validated());

        return back()->with('success', 'Product created successfully.');
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return back()->with('success', 'Product updated successfully.');
    }

    public function storeVariant(StoreProductVariantRequest $request, Product $product): RedirectResponse
    {
        $product->variants()->create($request->validated());

        return back()->with('success', 'Product variant created successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->variants()->exists()) {
            throw ValidationException::withMessages([
                'product' => 'Delete the related variants first before removing this product.',
            ]);
        }

        $product->delete();

        return back()->with('success', 'Product deleted successfully.');
    }
}
