<?php

use App\Enums\FixedAssetStatusEnum;
use App\Models\FixedAsset;
use App\Services\FixedAssets\FixedAssetDepreciationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('fixed-assets:depreciate', function (FixedAssetDepreciationService $service): int {
    $asOf = CarbonImmutable::now(config('app.timezone'));
    $processed = 0;
    $failed = 0;

    foreach (FixedAsset::query()
        ->where('status', FixedAssetStatusEnum::Posted->value)
        ->where('depreciation_enabled', true)
        ->lazyById(100) as $fixedAsset) {
        try {
            $processed += $service->depreciate($fixedAsset, $asOf);
        } catch (Throwable $exception) {
            report($exception);
            $this->error("Depreciation failed for asset {$fixedAsset->id}.");
            $failed++;
        }
    }

    $this->info("Recorded {$processed} depreciation entries; {$failed} assets failed.");

    return $failed > 0 ? 1 : 0;
})->purpose('Record completed monthly fixed asset depreciation periods');

Schedule::command('fixed-assets:depreciate')
    ->dailyAt('01:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();
