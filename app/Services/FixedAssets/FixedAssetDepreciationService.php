<?php

namespace App\Services\FixedAssets;

use App\Enums\FixedAssetStatusEnum;
use App\Models\FixedAsset;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FixedAssetDepreciationService
{
    /** @param array{depreciation_enabled: bool|int|string, depreciation_start_date?: string, useful_life_months?: int, residual_value?: numeric-string|float|int} $data */
    public function configure(FixedAsset $fixedAsset, array $data): FixedAsset
    {
        return DB::transaction(function () use ($fixedAsset, $data): FixedAsset {
            $fixedAsset = FixedAsset::query()->whereKey($fixedAsset->id)->lockForUpdate()->firstOrFail();

            if ($fixedAsset->status === FixedAssetStatusEnum::Cancelled || $fixedAsset->depreciations()->exists()) {
                throw ValidationException::withMessages([
                    'depreciation_enabled' => ['Depreciation settings cannot change for cancelled assets or after depreciation has been recorded.'],
                ]);
            }

            if ($data['depreciation_enabled']) {
                if (CarbonImmutable::parse($data['depreciation_start_date'])->startOfDay()->lt($fixedAsset->purchase_date->copy()->startOfDay())) {
                    throw ValidationException::withMessages(['depreciation_start_date' => ['The depreciation start date must be on or after the purchase date.']]);
                }

                if (BigDecimal::of((string) $data['residual_value'])->isGreaterThan($fixedAsset->purchase_amount)) {
                    throw ValidationException::withMessages(['residual_value' => ['The residual value must not exceed the purchase amount.']]);
                }
            }

            $fixedAsset->update([
                'depreciation_enabled' => (bool) $data['depreciation_enabled'],
                'depreciation_start_date' => $data['depreciation_enabled'] ? $data['depreciation_start_date'] : null,
                'useful_life_months' => $data['depreciation_enabled'] ? $data['useful_life_months'] : null,
                'residual_value' => $data['depreciation_enabled'] ? $data['residual_value'] : 0,
            ]);

            return $fixedAsset->refresh();
        });
    }

    public function depreciate(FixedAsset $fixedAsset, CarbonImmutable $asOf): int
    {
        return DB::transaction(function () use ($fixedAsset, $asOf): int {
            $fixedAsset = FixedAsset::query()->whereKey($fixedAsset->id)->lockForUpdate()->firstOrFail();

            if ($fixedAsset->status !== FixedAssetStatusEnum::Posted || ! $fixedAsset->depreciation_enabled) {
                return 0;
            }

            $start = CarbonImmutable::instance($fixedAsset->depreciation_start_date);
            $firstPeriod = $start->day === 1 ? $start->startOfMonth() : $start->addMonthNoOverflow()->startOfMonth();
            $latest = $fixedAsset->depreciations()->latest('period')->first();
            $period = $latest ? CarbonImmutable::instance($latest->period)->addMonth() : $firstPeriod;
            $end = $firstPeriod->addMonths($fixedAsset->useful_life_months);
            $cutoff = $asOf->setTimezone(config('app.timezone'))->startOfMonth();
            $purchase = BigDecimal::of($fixedAsset->purchase_amount);
            $residual = BigDecimal::of($fixedAsset->residual_value);
            $accumulated = BigDecimal::of($fixedAsset->accumulated_depreciation);
            $monthly = $purchase->minus($residual)->dividedBy($fixedAsset->useful_life_months, 2, RoundingMode::HalfUp);
            $count = 0;

            while ($period->lt($cutoff) && $period->lt($end)) {
                $opening = $purchase->minus($accumulated);
                $remaining = $opening->minus($residual);
                if ($remaining->isLessThanOrEqualTo(0)) {
                    break;
                }

                $amount = $period->addMonth()->eq($end) || $monthly->isGreaterThan($remaining) ? $remaining : $monthly;
                $fixedAsset->depreciations()->create([
                    'period' => $period->toDateString(),
                    'amount' => (string) $amount,
                    'opening_book_value' => (string) $opening,
                    'closing_book_value' => (string) $opening->minus($amount),
                ]);
                $accumulated = $accumulated->plus($amount);
                $period = $period->addMonth();
                $count++;
            }

            if ($count > 0) {
                $fixedAsset->accumulated_depreciation = (string) $accumulated;
                $fixedAsset->save();
            }

            return $count;
        });
    }
}
