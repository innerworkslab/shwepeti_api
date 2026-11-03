<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class ItemImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_imports_multiple_conversions_per_item_and_records_prices(): void
    {
        $actor = $this->prepareInventory();

        $this->postJson('/api/v1/admin/items/import', ['file' => $this->workbook()])
            ->assertCreated()
            ->assertJsonPath('data.imported_items', 3)
            ->assertJsonPath('data.imported_conversions', 3);

        $rice = Item::query()->where('code', 'RICE-SAMPLE')->firstOrFail();
        $this->assertDatabaseCount('items', 3);
        $this->assertDatabaseCount('item_unit_conversions', 3);
        $this->assertDatabaseHas('items', ['id' => $rice->id, 'barcode' => '0012345678901']);
        $this->assertDatabaseHas('item_price_histories', ['item_id' => $rice->id, 'new_price' => 2500, 'changed_by' => $actor->id]);
        $this->getJson('/api/v1/admin/items/'.$rice->id)->assertOk()
            ->assertJsonCount(2, 'data.item_unit_conversions')
            ->assertJsonPath('data.item_unit_conversions.0.conversion_factor', '1000.000000')
            ->assertJsonPath('data.item_unit_conversions.1.conversion_factor', '25.000000');
    }

    #[DataProvider('invalidRows')]
    public function test_invalid_rows_return_422_and_save_nothing(array $changes, string $error): void
    {
        $this->prepareInventory();

        $this->postJson('/api/v1/admin/items/import', ['file' => $this->workbook($changes)])
            ->assertUnprocessable()->assertJsonValidationErrors([$error]);

        $this->assertDatabaseCount('items', 0);
        $this->assertDatabaseCount('item_unit_conversions', 0);
        $this->assertDatabaseCount('item_price_histories', 0);
    }

    /** @return array<string, array{array<string, string>, string}> */
    public static function invalidRows(): array
    {
        return [
            'zero factor' => [['L4' => '0'], 'rows.4.conversion_factor'],
            'negative factor' => [['L4' => '-1'], 'rows.4.conversion_factor'],
            'factor rounds to zero' => [['L4' => '0.0000001'], 'rows.4.conversion_factor'],
            'unknown unit' => [['J4' => 'unknown'], 'rows.4.from_unit'],
            'same units' => [['K4' => 'kg'], 'rows.4.to_unit'],
            'missing factor' => [['L4' => ''], 'rows.4.conversion_factor'],
            'duplicate conversion' => [['J3' => 'kg', 'K3' => 'g'], 'rows.3.from_unit'],
            'conflicting item details' => [['B3' => 'Different rice'], 'rows.3.code'],
            'duplicate SKU' => [['E4' => 'SKU-RICE-SAMPLE'], 'rows.4.sku'],
            'unknown category' => [['C4' => 'UNKNOWN'], 'rows.4.category_code'],
            'unknown stock unit' => [['D4' => 'unknown'], 'rows.4.stock_unit'],
            'invalid active flag' => [['I4' => 'yes'], 'rows.4.is_active'],
            'price overflow' => [['G4' => '10000000000000000'], 'rows.4.price'],
            'stock overflow' => [['H4' => '1000000000000'], 'rows.4.min_stock'],
            'factor overflow' => [['L4' => '1000000000000'], 'rows.4.conversion_factor'],
        ];
    }

    public function test_existing_item_code_returns_422_without_overwriting_or_partially_importing(): void
    {
        $this->prepareInventory();
        $this->postJson('/api/v1/admin/items/import', ['file' => $this->workbook()])->assertCreated();

        $this->postJson('/api/v1/admin/items/import', ['file' => $this->workbook(['A5' => 'NEW-SALT'])])
            ->assertUnprocessable()->assertJsonValidationErrors(['rows.2.code']);

        $this->assertDatabaseCount('items', 3);
        $this->assertDatabaseCount('item_price_histories', 3);
        $this->assertDatabaseMissing('items', ['code' => 'NEW-SALT']);
    }

    public function test_missing_file_returns_422(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRoleEnum::HotelAdministrator]));

        $this->postJson('/api/v1/admin/items/import')->assertUnprocessable()->assertJsonValidationErrors(['file']);
    }

    public function test_shared_strings_from_excel_are_supported(): void
    {
        $this->prepareInventory();
        $file = $this->workbook();
        $zip = new ZipArchive;
        $zip->open($file->getPathname());
        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $strings = new \SimpleXMLElement('<sst xmlns="'.$namespace.'"/>');
        $index = 0;
        foreach ($sheet->xpath('//*[local-name()="c"][@t="str"]') as $cell) {
            $value = $cell->xpath('./*[local-name()="v"]')[0];
            $strings->addChild('si')->addChild('t', htmlspecialchars((string) $value, ENT_XML1));
            $value[0] = (string) $index++;
            $cell['t'] = 's';
        }
        $zip->addFromString('xl/sharedStrings.xml', $strings->asXML());
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet->asXML());
        $zip->close();

        $this->postJson('/api/v1/admin/items/import', ['file' => $file])
            ->assertCreated()->assertJsonPath('data.imported_items', 3)->assertJsonPath('data.imported_conversions', 3);
        $this->assertDatabaseHas('items', ['code' => 'RICE-SAMPLE', 'barcode' => '0012345678901']);
    }

    #[DataProvider('invalidHeaders')]
    public function test_invalid_headers_return_422_without_saving(array $changes): void
    {
        $this->prepareInventory();

        $this->postJson('/api/v1/admin/items/import', ['file' => $this->workbook($changes)])
            ->assertUnprocessable()->assertJsonValidationErrors(['file']);
        $this->assertDatabaseCount('items', 0);
    }

    /** @return array<string, array{array<string, string>}> */
    public static function invalidHeaders(): array
    {
        return [
            'missing code' => [['A1' => '']],
            'duplicate name' => [['A1' => 'name']],
            'misspelled conversion factor' => [['L1' => 'conversion_factors']],
        ];
    }

    public function test_import_requires_authentication(): void
    {
        $this->postJson('/api/v1/admin/items/import')->assertUnauthorized();
    }

    public function test_import_forbids_non_hotel_administrators(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRoleEnum::InventoryAdministrator]));

        $this->postJson('/api/v1/admin/items/import', ['file' => $this->workbook()])->assertForbidden();
        $this->assertDatabaseCount('items', 0);
    }

    public function test_administrator_can_download_sample_workbook(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRoleEnum::HotelAdministrator]));

        $this->get('/api/v1/admin/items/import-template')->assertOk()->assertDownload('item-import-sample.xlsx');
    }

    public function test_formulas_are_rejected_without_saving_items(): void
    {
        $this->prepareInventory();
        $file = $this->workbook();
        $zip = new ZipArchive;
        $zip->open($file->getPathname());
        $xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $xml->xpath('//*[local-name()="c"][@r="L4"]')[0]->addChild('f', '1000+1');
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml->asXML());
        $zip->close();

        $this->postJson('/api/v1/admin/items/import', ['file' => $file])->assertUnprocessable()->assertJsonValidationErrors(['rows.4']);
        $this->assertDatabaseCount('items', 0);
    }

    public function test_corrupt_workbook_returns_422(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRoleEnum::HotelAdministrator]));

        $this->postJson('/api/v1/admin/items/import', [
            'file' => UploadedFile::fake()->createWithContent('broken.xlsx', 'not an Excel workbook'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['file']);
    }

    private function prepareInventory(): User
    {
        $actor = User::factory()->create(['role' => UserRoleEnum::HotelAdministrator]);
        $this->actingAs($actor);
        $groupId = $this->postJson('/api/v1/admin/unit-groups', ['name' => 'Import Units'])->assertCreated()->json('data.id');
        foreach (['kg', 'g', 'box'] as $symbol) {
            $this->postJson('/api/v1/admin/units', ['unit_group_id' => $groupId, 'name' => $symbol, 'symbol' => $symbol])->assertCreated();
        }
        $this->postJson('/api/v1/admin/item-categories', ['name' => 'Raw Materials', 'code' => 'RAW_MATERIALS'])->assertCreated();

        return $actor;
    }

    /** @param array<string, string> $changes */
    private function workbook(array $changes = []): UploadedFile
    {
        $file = UploadedFile::fake()->createWithContent('items.xlsx', file_get_contents(resource_path('item-import-sample.xlsx')));
        if ($changes !== []) {
            $zip = new ZipArchive;
            $zip->open($file->getPathname());
            $xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
            foreach ($changes as $address => $value) {
                $cell = $xml->xpath('//*[local-name()="c"][@r="'.$address.'"]')[0];
                unset($cell->v, $cell->is);
                $cell['t'] = 'inlineStr';
                $cell->addChild('is')->addChild('t', htmlspecialchars($value, ENT_XML1));
            }
            $zip->addFromString('xl/worksheets/sheet1.xml', $xml->asXML());
            $zip->close();
        }

        return $file;
    }
}
