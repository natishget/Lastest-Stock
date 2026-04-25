<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
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
        $warehouse = $this->route('warehouse');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('warehouses', 'name')->ignore($warehouse?->id)],
            'location' => ['nullable', 'string'],
        ];
    }
}
