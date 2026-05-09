<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductImportRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\StoreProductVariantRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        $variants = ProductVariant::query()
            ->with(['product'])
            ->select('product_variants.*')
            ->join('products as product_names', 'product_names.id', '=', 'product_variants.product_id')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($variantQuery) use ($search): void {
                    $variantQuery
                        ->where('sku', 'like', "%{$search}%")
                        ->orWhereHas('product', function ($productQuery) use ($search): void {
                            $productQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($origin !== '', function ($query) use ($origin): void {
                $query->where('origin', $origin);
            })
            ->when($color !== '', function ($query) use ($color): void {
                $query->where('color', $color);
            })
            ->orderBy('product_names.name')
            ->orderBy('sku')
            ->paginate(10)
            ->withQueryString()
            ->through(function (ProductVariant $variant): array {
                return [
                    'id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'product_name' => $variant->product?->name,
                    'base_unit' => $variant->product?->base_unit,
                    'origin' => $variant->origin,
                    'color' => $variant->color,
                    'sku' => $variant->sku,
                    'thickness' => $variant->thickness !== null ? (string) $variant->thickness : null,
                    'size' => $variant->size,
                    'created_at' => $variant->created_at,
                ];
            });

        return Inertia::render('products/index', [
            'variants' => $variants,
            'filters' => [
                'search' => $search,
                'origin' => $origin,
                'color' => $color,
            ],
            'productOptions' => Product::query()
                ->orderBy('name')
                ->get(['id', 'name', 'base_unit'])
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'base_unit' => $product->base_unit,
                ])
                ->all(),
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

    public function import(): Response
    {
        return Inertia::render('products/import');
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        Product::query()->create($request->validated());

        return back()->with('success', 'Product created successfully.');
    }

    public function storeImport(StoreProductImportRequest $request): RedirectResponse
    {
        $products = $request->products();
        $productNames = array_map(static fn (array $product): string => $product['name'], $products);
        $existingNames = Product::query()
            ->whereIn('name', $productNames)
            ->pluck('name')
            ->all();

        if ($existingNames !== []) {
            throw ValidationException::withMessages([
                'products_json' => 'The JSON payload contains product names that already exist: '.implode(', ', $existingNames).'.',
            ]);
        }

        DB::transaction(function () use ($products): void {
            $now = now();

            Product::query()->insert(array_map(static function (array $product) use ($now): array {
                return [
                    'id' => (string) Str::uuid(),
                    'name' => $product['name'],
                    'base_unit' => $product['base_unit'],
                    'created_at' => $now,
                ];
            }, $products));
        });

        return redirect()->route('products.index')->with('success', count($products).' products imported successfully.');
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return back()->with('success', 'Product updated successfully.');
    }

    public function storeVariant(StoreProductVariantRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        ProductVariant::query()->create([
            'product_id' => $validated['product_id'],
            'color' => $validated['color'] ?? null,
            'origin' => $validated['origin'] ?? null,
            'sku' => $validated['sku'] ?? null,
            'thickness' => $validated['thickness'] ?? null,
            'size' => $validated['size'] ?? null,
        ]);

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
