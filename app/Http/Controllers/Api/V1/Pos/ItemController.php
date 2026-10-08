<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Enums\ServiceOrderTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\Items\ListItemRequest;
use App\Http\Resources\Items\ItemResource;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ItemController extends Controller
{
    public function index(ListItemRequest $request): JsonResponse
    {
        $filters = array_merge($request->validated(), [
            'is_active' => true,
        ]);

        if (($filters['category'] ?? null) && ! isset($filters['item_category_id'])) {
            $filters['item_category_id'] = ItemCategory::query()
                ->where('code', $this->categoryCode($filters['category']))
                ->value('id');
        }

        unset($filters['category']);

        $query = Item::query()
            ->with(['itemCategory', 'stockUnit'])
            ->filter($filters)
            ->latest();

        $items = isset($filters['page'])
            ? $query->paginate((int) ($filters['per_page'] ?? 15))
            : $query->get();

        return ApiResponse::resource('Items retrieved successfully.', ItemResource::collection($items));
    }

    private function categoryCode(string $category): string
    {
        return match ($category) {
            ServiceOrderTypeEnum::Amenity->value => 'AMENITIES',
            ServiceOrderTypeEnum::Laundry->value => 'LAUNDRY',
        };
    }
}
