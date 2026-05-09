<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use JsonException;

class StoreProductImportRequest extends FormRequest
{
    private const MAX_PRODUCTS = 250;

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
            'products_json' => ['required', 'string', 'max:50000'],
        ];
    }

    /**
     * @return array<int, array{name: string, base_unit: string}>
     */
    public function products(): array
    {
        $this->validated();

        try {
            $decoded = json_decode(trim($this->string('products_json')->toString()), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'products_json' => 'The JSON payload must be valid JSON.',
            ]);
        }

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw ValidationException::withMessages([
                'products_json' => 'The JSON payload must be an array of product objects.',
            ]);
        }

        if (count($decoded) === 0) {
            throw ValidationException::withMessages([
                'products_json' => 'The JSON payload must contain at least one product.',
            ]);
        }

        if (count($decoded) > self::MAX_PRODUCTS) {
            throw ValidationException::withMessages([
                'products_json' => 'You can import up to '.self::MAX_PRODUCTS.' products at a time.',
            ]);
        }

        $products = [];
        $normalizedNames = [];

        foreach ($decoded as $index => $item) {
            $itemNumber = $index + 1;

            if (! is_array($item)) {
                throw ValidationException::withMessages([
                    'products_json' => "Product #{$itemNumber} must be a JSON object.",
                ]);
            }

            $extraKeys = array_diff(array_keys($item), ['name', 'unit']);

            if ($extraKeys !== []) {
                throw ValidationException::withMessages([
                    'products_json' => 'Product #'.$itemNumber.' contains unsupported fields: '.implode(', ', $extraKeys).'.',
                ]);
            }

            $name = trim((string) ($item['name'] ?? ''));
            $unit = trim((string) ($item['unit'] ?? ''));

            if ($name === '' || mb_strlen($name) > 255) {
                throw ValidationException::withMessages([
                    'products_json' => 'Product #'.$itemNumber.' must have a valid name that is 255 characters or fewer.',
                ]);
            }

            if ($unit === '' || mb_strlen($unit) > 50) {
                throw ValidationException::withMessages([
                    'products_json' => 'Product #'.$itemNumber.' must have a valid unit that is 50 characters or fewer.',
                ]);
            }

            $normalizedName = mb_strtolower($name);

            if (in_array($normalizedName, $normalizedNames, true)) {
                throw ValidationException::withMessages([
                    'products_json' => 'Duplicate product names were found inside the JSON payload.',
                ]);
            }

            $normalizedNames[] = $normalizedName;
            $products[] = [
                'name' => $name,
                'base_unit' => $unit,
            ];
        }

        return $products;
    }
}
