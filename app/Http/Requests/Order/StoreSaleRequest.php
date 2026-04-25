<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
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
            'customer_name' => ['nullable', 'string', 'max:255'],
            'sale_date' => ['nullable', 'date'],
            'warehouse_id' => ['required', 'uuid', Rule::exists('warehouses', 'id')],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'uuid', Rule::exists('product_variants', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.selling_price' => ['required', 'numeric', 'gte:0'],
        ];
    }
}
