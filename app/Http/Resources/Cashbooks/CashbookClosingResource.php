<?php

namespace App\Http\Resources\Cashbooks;

use App\Http\Resources\Concerns\FormatsDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashbookClosingResource extends JsonResource
{
    use FormatsDateTime;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cashbook_id' => $this->cashbook_id,
            'closing_date' => $this->closing_date->toDateString(),
            'closed_at' => $this->dateTime($this->closed_at),
            'opening_balance' => $this->opening_balance,
            'closing_balance' => $this->closing_balance,
            'remark' => $this->remark,
            'created_by' => $this->created_by,
        ];
    }
}
