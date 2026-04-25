<?php

namespace App\Http\Requests\Product;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'uuid', Rule::exists(Product::class, 'id')],
            'color' => ['nullable', 'string', 'max:50'],
            'origin' => ['nullable', Rule::in(['LOCAL', 'IMPORTED'])],
            'sku' => ['nullable', 'string', 'max:100', 'unique:product_variants,sku'],
            'thickness' => ['nullable', 'numeric', 'min:0'],
            'size' => ['nullable', 'string', 'max:100'],
        ];
    }
}
