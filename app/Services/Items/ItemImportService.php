<?php

namespace App\Services\Items;

use App\Http\Requests\Items\SaveItemRequest;
use App\Models\ItemCategory;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ItemImportService
{
    public function __construct(
        private readonly ItemImportWorkbook $workbook,
        private readonly ItemService $itemService,
    ) {}

    /** @return array{imported_items: int, imported_conversions: int} */
    public function import(UploadedFile $file, User $actor): array
    {
        $rows = $this->workbook->rows($file);
        $categories = ItemCategory::query()->where('is_active', true)->get()->groupBy('code');
        $units = Unit::query()->where('is_active', true)->get()->groupBy('symbol');
        $rules = (new SaveItemRequest)->rules();
        $rules = array_filter($rules, fn (string $key): bool => ! str_starts_with($key, 'item_unit_conversions') && ! in_array($key, ['id', 'image'], true), ARRAY_FILTER_USE_KEY);
        $rules['price'][] = 'lt:10000000000000000';
        $rules['min_stock'][] = 'lt:1000000000000';
        $items = [];
        $errors = [];
        $identifiers = [];
        $conversionCount = 0;

        foreach ($rows as $number => $row) {
            $data = array_intersect_key($row, $rules);
            $data['name'] = preg_replace('/\s+/', ' ', $data['name'] ?? '');
            $category = $categories->get($row['category_code'] ?? '');
            $stockUnit = $units->get($row['stock_unit'] ?? '');
            $data['item_category_id'] = $category?->count() === 1 ? $category->first()->id : null;
            $data['stock_unit_id'] = $stockUnit?->count() === 1 ? $stockUnit->first()->id : null;
            $data['is_active'] = $data['is_active'] ?? '1';
            $validator = Validator::make($data, $rules);
            foreach ($validator->errors()->messages() as $field => $messages) {
                $errors["rows.{$number}.{$field}"] = $messages;
            }
            if ($data['item_category_id'] === null) {
                $errors["rows.{$number}.category_code"] = ['Use an existing, active category code.'];
            }
            if ($data['stock_unit_id'] === null) {
                $errors["rows.{$number}.stock_unit"] = ['Use an existing, active, unambiguous unit symbol.'];
            }
            if ($validator->fails()) {
                continue;
            }

            $code = $data['code'];
            if (isset($items[$code]) && $items[$code]['data'] !== $data) {
                $errors["rows.{$number}.code"] = ['Repeat the same item details on every row with this code.'];

                continue;
            }
            if (! isset($items[$code])) {
                foreach (['sku', 'barcode'] as $field) {
                    if (filled($data[$field] ?? null)) {
                        if (isset($identifiers[$field][$data[$field]])) {
                            $errors["rows.{$number}.{$field}"] = ['This value is already used by another item in the workbook.'];
                        }
                        $identifiers[$field][$data[$field]] = true;
                    }
                }
                $items[$code] = ['data' => $data, 'conversions' => []];
            }

            if (filled($row['from_unit'] ?? null) || filled($row['to_unit'] ?? null) || filled($row['conversion_factor'] ?? null) || filled($row['conversion_is_active'] ?? null)) {
                $conversionValidator = Validator::make($row, [
                    'from_unit' => ['required', 'string'],
                    'to_unit' => ['required', 'string', 'different:from_unit'],
                    'conversion_factor' => ['required', 'numeric', 'min:0.000001', 'lt:1000000000000'],
                    'conversion_is_active' => ['nullable', 'boolean'],
                ]);
                foreach ($conversionValidator->errors()->messages() as $field => $messages) {
                    $errors["rows.{$number}.{$field}"] = $messages;
                }
                $fromUnit = $units->get($row['from_unit'] ?? '');
                $toUnit = $units->get($row['to_unit'] ?? '');
                foreach (['from_unit' => $fromUnit, 'to_unit' => $toUnit] as $field => $matches) {
                    if ($matches?->count() !== 1) {
                        $errors["rows.{$number}.{$field}"] = ['Use an existing, active, unambiguous unit symbol.'];
                    }
                }
                if ($conversionValidator->fails() || $fromUnit?->count() !== 1 || $toUnit?->count() !== 1) {
                    continue;
                }
                $pair = $fromUnit->first()->id.'-'.$toUnit->first()->id;
                if (isset($items[$code]['conversions'][$pair])) {
                    $errors["rows.{$number}.from_unit"] = ['This conversion pair is repeated for the same item.'];

                    continue;
                }
                $items[$code]['conversions'][$pair] = [
                    'from_unit_id' => $fromUnit->first()->id,
                    'to_unit_id' => $toUnit->first()->id,
                    'conversion_factor' => $row['conversion_factor'],
                    'is_active' => $row['conversion_is_active'] ?? '1',
                ];
                $conversionCount++;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        try {
            DB::transaction(function () use ($items, $actor): void {
                foreach ($items as $item) {
                    $this->itemService->save([
                        ...$item['data'],
                        'item_unit_conversions' => array_values($item['conversions']),
                    ], $actor);
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['file' => 'An item code, SKU or barcode already exists. No items were imported.']);
        }

        return ['imported_items' => count($items), 'imported_conversions' => $conversionCount];
    }
}
