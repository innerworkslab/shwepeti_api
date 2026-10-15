<?php

namespace App\Http\Resources\FixedAssets;

use App\Http\Resources\Concerns\FormatsDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FixedAssetDepreciationResource extends JsonResource
{
    use FormatsDateTime;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fixed_asset_id' => $this->fixed_asset_id,
            'period' => $this->period->format('Y-m'),
            'amount' => $this->amount,
            'opening_book_value' => $this->opening_book_value,
            'closing_book_value' => $this->closing_book_value,
            'created_at' => $this->dateTime($this->created_at),
        ];
    }
}
