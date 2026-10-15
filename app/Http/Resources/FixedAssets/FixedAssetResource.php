<?php

namespace App\Http\Resources\FixedAssets;

use App\Http\Resources\AssetCategories\AssetCategoryResource;
use App\Http\Resources\Cashbooks\CashbookResource;
use App\Http\Resources\CashbookTransactions\CashbookTransactionResource;
use App\Http\Resources\Concerns\FormatsDateTime;
use App\Http\Resources\Users\UserResource;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FixedAssetResource extends JsonResource
{
    use FormatsDateTime;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'asset_code' => $this->asset_code,
            'asset_category_id' => $this->asset_category_id,
            'cashbook_id' => $this->cashbook_id,
            'name' => $this->name,
            'serial_number' => $this->serial_number,
            'location' => $this->location,
            'purchase_date' => $this->dateTime($this->purchase_date),
            'purchase_amount' => $this->purchase_amount,
            'depreciation_enabled' => $this->depreciation_enabled,
            'depreciation_method' => $this->depreciation_enabled ? 'straight_line' : null,
            'depreciation_start_date' => $this->depreciation_start_date?->format('Y-m-d'),
            'useful_life_months' => $this->useful_life_months,
            'residual_value' => $this->residual_value,
            'accumulated_depreciation' => $this->accumulated_depreciation,
            'book_value' => (string) BigDecimal::of($this->purchase_amount)->minus($this->accumulated_depreciation)->toScale(2),
            'warranty_expiry_date' => $this->warranty_expiry_date?->format('Y-m-d'),
            'documents' => $this->documents,
            'status' => $this->status->value,
            'remark' => $this->remark,
            'created_by' => $this->created_by,
            'asset_category' => AssetCategoryResource::make($this->whenLoaded('assetCategory')),
            'cashbook' => CashbookResource::make($this->whenLoaded('cashbook')),
            'cashbook_transaction' => CashbookTransactionResource::make($this->whenLoaded('cashbookTransaction')),
            'creator' => UserResource::make($this->whenLoaded('creator')),
            'created_at' => $this->dateTime($this->created_at),
            'updated_at' => $this->dateTime($this->updated_at),
        ];
    }
}
