<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class FixedAssetDepreciationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_catches_up_completed_months_and_retries_without_changing_cash(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-04-01 01:00:00', 'Asia/Yangon'));
        $asset = $this->depreciableAsset();

        $this->artisan('fixed-assets:depreciate')->assertSuccessful();
        $this->artisan('fixed-assets:depreciate')->assertSuccessful();

        $this->assertDatabaseCount('fixed_asset_depreciations', 3);
        $this->assertDatabaseHas('fixed_asset_depreciations', ['fixed_asset_id' => $asset->id, 'amount' => '300.00', 'closing_book_value' => '100.00']);
        $this->assertSame('900.00', $asset->refresh()->accumulated_depreciation);
        $this->assertDatabaseCount('cashbook_transactions', 0);
        $this->assertSame('10000.00', $asset->cashbook->current_balance);

        $this->actingAs(User::factory()->create(['role' => UserRoleEnum::HotelAdministrator]));
        $this->getJson("/api/v1/admin/fixed-assets/{$asset->id}")
            ->assertOk()->assertJsonPath('data.book_value', '100.00')->assertJsonPath('data.accumulated_depreciation', '900.00');
        $this->getJson("/api/v1/admin/fixed-assets/{$asset->id}/depreciations")
            ->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('data.0.period', '2026-03');
    }

    #[TestWith(['100.00', '0.00', 3, ['33.33', '33.33', '33.34']])]
    #[TestWith(['0.01', '0.00', 3, ['0.00', '0.00', '0.01']])]
    #[TestWith(['100.00', '100.00', 3, []])]
    #[TestWith(['0.02', '0.00', 3, ['0.01', '0.01']])]
    public function test_rounding_never_depreciates_below_residual_value(string $cost, string $residual, int $months, array $expected): void
    {
        $this->travelTo(CarbonImmutable::parse('2027-01-01'));
        $asset = $this->depreciableAsset(['purchase_amount' => $cost, 'residual_value' => $residual, 'useful_life_months' => $months]);

        $this->artisan('fixed-assets:depreciate')->assertSuccessful();

        $this->assertSame($expected, $asset->depreciations()->orderBy('period')->get()->pluck('amount')->all());
        $this->assertSame($residual, $asset->depreciations()->latest('period')->first()?->closing_book_value ?? $asset->purchase_amount);
    }

    public function test_mid_month_start_skips_partial_month_and_current_month(): void
    {
        $asset = $this->depreciableAsset(['depreciation_start_date' => '2026-01-15']);
        $this->travelTo(CarbonImmutable::parse('2026-02-28 23:59:59', 'Asia/Yangon'));
        $this->artisan('fixed-assets:depreciate')->assertSuccessful();
        $this->assertDatabaseCount('fixed_asset_depreciations', 0);

        $this->travelTo(CarbonImmutable::parse('2026-03-01 00:00:00', 'Asia/Yangon'));
        $this->artisan('fixed-assets:depreciate')->assertSuccessful();
        $this->assertDatabaseCount('fixed_asset_depreciations', 1);
        $this->assertSame('2026-02-01', $asset->depreciations()->sole()->period->toDateString());
    }

    #[TestWith(['draft', true, '2026-01-01'])]
    #[TestWith(['cancelled', true, '2026-01-01'])]
    #[TestWith(['posted', false, '2026-01-01'])]
    #[TestWith(['posted', true, '2027-01-01'])]
    public function test_ineligible_assets_are_skipped(string $status, bool $enabled, string $start): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-04-01'));
        $this->depreciableAsset(['status' => $status, 'depreciation_enabled' => $enabled, 'depreciation_start_date' => $start]);

        $this->artisan('fixed-assets:depreciate')->assertSuccessful();

        $this->assertDatabaseCount('fixed_asset_depreciations', 0);
    }

    public function test_existing_posted_assets_can_be_configured_without_changing_purchase_details(): void
    {
        $asset = FixedAsset::factory()->create(['status' => 'posted']);
        $this->actingAs(User::factory()->create(['role' => UserRoleEnum::HotelAdministrator]));

        $this->postJson("/api/v1/admin/fixed-assets/{$asset->id}/depreciation-settings", $this->settings() + ['purchase_amount' => 1])
            ->assertOk()->assertJsonPath('data.depreciation_enabled', true)->assertJsonPath('data.purchase_amount', '1000.00');

        $this->assertDatabaseHas('fixed_assets', ['id' => $asset->id, 'depreciation_enabled' => true, 'useful_life_months' => 3]);
        $this->postJson("/api/v1/admin/fixed-assets/{$asset->id}/depreciation-settings", ['depreciation_enabled' => false])
            ->assertOk()->assertJsonPath('data.depreciation_start_date', null);
        $this->assertFalse($asset->refresh()->depreciation_enabled);
    }

    public function test_settings_cannot_change_after_history_exists(): void
    {
        $entry = FixedAssetDepreciation::factory()->create();
        $this->actingAs(User::factory()->create(['role' => UserRoleEnum::HotelAdministrator]));

        $this->postJson("/api/v1/admin/fixed-assets/{$entry->fixed_asset_id}/depreciation-settings", ['depreciation_enabled' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('depreciation_enabled');

        $this->assertTrue($entry->fixedAsset->depreciation_enabled);
        $this->assertDatabaseCount('fixed_asset_depreciations', 1);
    }

    #[TestWith(['depreciation_start_date', '2025-12-31'])]
    #[TestWith(['depreciation_start_date', 'invalid'])]
    #[TestWith(['useful_life_months', 0])]
    #[TestWith(['useful_life_months', 1201])]
    #[TestWith(['residual_value', -1])]
    #[TestWith(['residual_value', '1000.01'])]
    #[TestWith(['residual_value', '1.001'])]
    public function test_invalid_settings_return_422_without_changes(string $field, mixed $value): void
    {
        $asset = FixedAsset::factory()->create(['status' => 'posted']);
        $this->actingAs(User::factory()->create(['role' => UserRoleEnum::HotelAdministrator]));

        $this->postJson("/api/v1/admin/fixed-assets/{$asset->id}/depreciation-settings", array_replace($this->settings(), [$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertFalse($asset->refresh()->depreciation_enabled);
    }

    public function test_draft_save_accepts_depreciation_and_requires_settings_when_enabled(): void
    {
        $asset = FixedAsset::factory()->create();
        $this->actingAs(User::factory()->create(['role' => UserRoleEnum::HotelAdministrator]));
        $data = $asset->only(['id', 'asset_code', 'asset_category_id', 'cashbook_id', 'name', 'purchase_amount']);
        $data['purchase_date'] = '2026-01-01';

        $this->postJson('/api/v1/admin/fixed-assets', $data + ['depreciation_enabled' => true])
            ->assertUnprocessable()->assertJsonValidationErrors(['depreciation_start_date', 'useful_life_months', 'residual_value']);
        $this->postJson('/api/v1/admin/fixed-assets', $data + $this->settings())
            ->assertOk()->assertJsonPath('data.depreciation_enabled', true);
        $this->assertSame(3, $asset->refresh()->useful_life_months);
    }

    public function test_depreciation_endpoints_require_admin_access(): void
    {
        $asset = FixedAsset::factory()->create();
        $this->getJson("/api/v1/admin/fixed-assets/{$asset->id}/depreciations")->assertUnauthorized();
        $this->postJson("/api/v1/admin/fixed-assets/{$asset->id}/depreciation-settings", $this->settings())->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => UserRoleEnum::InventoryAdministrator]));
        $this->getJson("/api/v1/admin/fixed-assets/{$asset->id}/depreciations")->assertForbidden();
        $this->postJson("/api/v1/admin/fixed-assets/{$asset->id}/depreciation-settings", $this->settings())->assertForbidden();
    }

    public function test_draft_purchase_changes_revalidate_existing_depreciation_settings(): void
    {
        $asset = $this->depreciableAsset(['status' => 'draft']);
        $this->actingAs(User::factory()->create(['role' => UserRoleEnum::HotelAdministrator]));
        $data = $asset->only(['id', 'asset_code', 'asset_category_id', 'cashbook_id', 'name', 'purchase_amount']);
        $data['purchase_date'] = '2026-02-01';

        $this->postJson('/api/v1/admin/fixed-assets', $data)
            ->assertUnprocessable()->assertJsonValidationErrors('depreciation_start_date');
        $this->assertSame('2026-01-01', $asset->refresh()->purchase_date->toDateString());

        $data['purchase_date'] = '2026-01-01';
        $data['purchase_amount'] = '99.00';
        $this->postJson('/api/v1/admin/fixed-assets', $data)
            ->assertUnprocessable()->assertJsonValidationErrors('residual_value');
        $this->assertSame('1000.00', $asset->refresh()->purchase_amount);
    }

    public function test_cancelled_assets_reject_settings_changes(): void
    {
        $asset = FixedAsset::factory()->create(['status' => 'cancelled']);
        $this->actingAs(User::factory()->create(['role' => UserRoleEnum::HotelAdministrator]));

        $this->postJson("/api/v1/admin/fixed-assets/{$asset->id}/depreciation-settings", $this->settings())
            ->assertUnprocessable()->assertJsonValidationErrors('depreciation_enabled');
        $this->assertFalse($asset->refresh()->depreciation_enabled);
    }

    public function test_failed_asset_rolls_back_its_history_and_other_assets_continue(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-04-01'));
        $failedAsset = $this->depreciableAsset();
        $healthyAsset = $this->depreciableAsset();
        Exceptions::fake();
        FixedAssetDepreciation::creating(function (FixedAssetDepreciation $entry) use ($failedAsset): void {
            if ($entry->fixed_asset_id === $failedAsset->id && $entry->period->format('Y-m') === '2026-02') {
                throw new \RuntimeException('Simulated write failure');
            }
        });

        try {
            $this->artisan('fixed-assets:depreciate')->assertFailed();
            $this->assertSame(0, $failedAsset->depreciations()->count());
            $this->assertSame('0.00', $failedAsset->refresh()->accumulated_depreciation);
            $this->assertSame(3, $healthyAsset->depreciations()->count());
            Exceptions::assertReported(\RuntimeException::class);
        } finally {
            FixedAssetDepreciation::flushEventListeners();
        }

        $this->artisan('fixed-assets:depreciate')->assertSuccessful();
        $this->assertSame(3, $failedAsset->depreciations()->count());
    }

    /** @param array<string, mixed> $overrides */
    private function depreciableAsset(array $overrides = []): FixedAsset
    {
        return FixedAsset::factory()->create(array_replace(['status' => 'posted'], $this->settings(), $overrides));
    }

    /** @return array{depreciation_enabled: bool, depreciation_start_date: string, useful_life_months: int, residual_value: string} */
    private function settings(): array
    {
        return ['depreciation_enabled' => true, 'depreciation_start_date' => '2026-01-01', 'useful_life_months' => 3, 'residual_value' => '100.00'];
    }
}
