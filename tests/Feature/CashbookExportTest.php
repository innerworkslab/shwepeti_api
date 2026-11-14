<?php

namespace Tests\Feature;

use App\Models\Cashbook;
use App\Models\CashbookClosing;
use App\Models\User;
use App\Services\CashbookTransactions\CashbookTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use SimpleXMLElement;
use Tests\TestCase;
use ZipArchive;

class CashbookExportTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('exports')]
    public function test_export_filters_cashbook_and_inclusive_dates_without_pagination(string $portal, string $kind): void
    {
        $this->authenticate($portal);
        $cashbook = $this->cashbook();
        foreach (['2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23'] as $date) {
            $this->record($cashbook, $kind, $date);
        }
        $this->record($this->cashbook(), $kind, '2026-10-21');
        $cashbook->update(['is_active' => false]);

        $response = $this->getJson("/api/v1/{$portal}/cashbooks/{$cashbook->id}/{$kind}/export?date_from=2026-10-21&date_to=2026-10-22&page=1&per_page=1")
            ->assertOk()->assertDownload("cashbook-{$kind}_{$cashbook->id}.xlsx")
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $sheet = $this->sheet($response->streamedContent());
        $this->assertCount(3, $sheet->sheetData->row);
        $this->assertStringContainsString('2026-10-21', $sheet->asXML());
        $this->assertStringContainsString('2026-10-22', $sheet->asXML());
        $this->assertStringNotContainsString('2026-10-20', $sheet->asXML());
        $this->assertStringNotContainsString('2026-10-23', $sheet->asXML());
        $this->assertStringContainsString('မြန်မာ', $sheet->asXML());
        $this->assertStringNotContainsString('<f>', $sheet->asXML());
        foreach (array_slice(iterator_to_array($sheet->sheetData->row, false), 1) as $row) {
            $this->assertSame((string) $cashbook->id, (string) $row->c[1]->v);
        }
    }

    #[DataProvider('exports')]
    public function test_export_supports_optional_date_bounds_and_empty_results(string $portal, string $kind): void
    {
        $this->authenticate($portal);
        $cashbook = $this->cashbook();
        $this->record($cashbook, $kind, '2026-10-21');
        $this->record($cashbook, $kind, '2026-10-22');
        $url = "/api/v1/{$portal}/cashbooks/{$cashbook->id}/{$kind}/export";

        foreach (['' => 3, '?date_from=2026-10-22' => 2, '?date_to=2026-10-21' => 2, '?date_from=2026-10-23' => 1] as $query => $rows) {
            $response = $this->getJson($url.$query)->assertOk();
            $this->assertCount($rows, $this->sheet($response->streamedContent())->sheetData->row);
        }
    }

    #[DataProvider('exports')]
    public function test_export_returns_422_for_invalid_dates(string $portal, string $kind): void
    {
        $this->authenticate($portal);
        $cashbook = $this->cashbook();
        $url = "/api/v1/{$portal}/cashbooks/{$cashbook->id}/{$kind}/export";

        $this->getJson($url.'?date_from=invalid')->assertUnprocessable()->assertJsonValidationErrors('date_from');
        $this->getJson($url.'?date_to=2026-02-30')->assertUnprocessable()->assertJsonValidationErrors('date_to');
        $this->getJson($url.'?date_from=2026-10-22&date_to=2026-10-21')->assertUnprocessable()->assertJsonValidationErrors('date_to');
    }

    #[DataProvider('exports')]
    public function test_export_requires_authentication_and_permission(string $portal, string $kind): void
    {
        $cashbook = $this->cashbook();
        $url = "/api/v1/{$portal}/cashbooks/{$cashbook->id}/{$kind}/export";
        $this->getJson($url)->assertUnauthorized();

        $this->actingAs(User::factory()->create(['role' => 'restaurant_administrator', 'portal_access' => $portal]), 'sanctum');
        $this->getJson($url)->assertForbidden();
    }

    #[DataProvider('exports')]
    public function test_export_returns_404_for_missing_cashbooks(string $portal, string $kind): void
    {
        $this->authenticate($portal);
        $this->getJson("/api/v1/{$portal}/cashbooks/999999/{$kind}/export")->assertNotFound();
    }

    public function test_pos_cannot_export_non_pos_cashbooks(): void
    {
        $this->authenticate('pos');
        $cashbook = $this->cashbook();
        $cashbook->update(['type' => 'cash']);

        foreach (['transactions', 'closings'] as $kind) {
            $this->getJson("/api/v1/pos/cashbooks/{$cashbook->id}/{$kind}/export")->assertNotFound();
        }
    }

    public static function exports(): array
    {
        return [
            'admin transactions' => ['admin', 'transactions'],
            'admin closings' => ['admin', 'closings'],
            'pos transactions' => ['pos', 'transactions'],
            'pos closings' => ['pos', 'closings'],
        ];
    }

    private function authenticate(string $portal): void
    {
        $this->actingAs(User::factory()->withPermissions()->create([
            'role' => 'hotel_administrator',
            'portal_access' => $portal,
        ]), 'sanctum');
    }

    private function cashbook(): Cashbook
    {
        return Cashbook::query()->create([
            'code' => fake()->unique()->bothify('CB-########'),
            'name' => 'POS Cash',
            'type' => 'pos_cash',
            'opening_balance' => 1000,
            'is_active' => true,
        ]);
    }

    private function record(Cashbook $cashbook, string $kind, string $date): void
    {
        if ($kind === 'closings') {
            CashbookClosing::factory()->for($cashbook)->create([
                'closing_date' => $date,
                'closed_at' => $date.' 23:59:59',
                'remark' => '=မြန်မာ & <test>',
            ]);

            return;
        }

        app(CashbookTransactionService::class)->post([
            'cashbook_id' => $cashbook->id,
            'transaction_type' => 'income',
            'amount' => 25.50,
            'transaction_date' => $date.' 23:59:59',
            'remark' => '=မြန်မာ & <test>',
        ]);
    }

    private function sheet(string $contents): SimpleXMLElement
    {
        $path = tempnam(sys_get_temp_dir(), 'cashbook-test-');
        file_put_contents($path, $contents);
        $zip = new ZipArchive;
        try {
            $this->assertTrue($zip->open($path));
            $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
            $this->assertInstanceOf(SimpleXMLElement::class, $sheet);

            return $sheet;
        } finally {
            $zip->close();
            unlink($path);
        }
    }
}
