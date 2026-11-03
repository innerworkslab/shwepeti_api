<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Models\Item;
use App\Models\ItemUnitConversion;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

class MenuImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_imports_optional_recipes_and_both_conversion_directions(): void
    {
        $this->prepareInventory();

        $this->postJson('/api/v1/admin/menus/import', ['file' => $this->workbook()])->assertCreated()
            ->assertJsonPath('data.imported_menus', 3)->assertJsonPath('data.imported_recipes', 3);

        $this->assertDatabaseCount('menus', 3);
        $this->assertDatabaseCount('menu_recipes', 3);
        $rice = Menu::query()->where('code', 'RICE_PLATE_SAMPLE')->firstOrFail();
        $bulk = Menu::query()->where('code', 'BULK_RICE_SAMPLE')->firstOrFail();
        $tea = Menu::query()->where('code', 'TEA_SAMPLE')->firstOrFail();
        $this->assertDatabaseHas('menu_recipes', ['menu_id' => $rice->id, 'quantity' => 250, 'base_quantity' => 0.25]);
        $this->assertDatabaseHas('menu_recipes', ['menu_id' => $bulk->id, 'quantity' => 1, 'base_quantity' => 25]);
        $this->assertDatabaseMissing('menu_recipes', ['menu_id' => $tea->id]);
    }

    #[DataProvider('invalidRows')]
    public function test_invalid_import_returns_422_without_saving_any_menus(array $changes, string $error): void
    {
        $this->prepareInventory();

        $this->postJson('/api/v1/admin/menus/import', ['file' => $this->workbook($changes)])
            ->assertUnprocessable()->assertJsonValidationErrors([$error]);

        $this->assertDatabaseCount('menus', 0);
        $this->assertDatabaseCount('menu_recipes', 0);
    }

    /** @return array<string, array{array<string, string>, string}> */
    public static function invalidRows(): array
    {
        return [
            'unit not configured for this item' => [['J3' => 'g'], 'rows.3.recipe_unit'],
            'unrelated existing unit' => [['J3' => 'l'], 'rows.3.recipe_unit'],
            'unknown item' => [['I3' => 'MISSING'], 'rows.3.recipe_item_code'],
            'missing quantity' => [['K3' => ''], 'rows.3.recipe_quantity'],
            'zero quantity' => [['K3' => '0'], 'rows.3.recipe_quantity'],
            'negative quantity' => [['K3' => '-1'], 'rows.3.recipe_quantity'],
            'quantity overflow' => [['K3' => '1000000000000'], 'rows.3.recipe_quantity'],
            'converted quantity overflow' => [['K4' => '999999999999'], 'rows.4.recipe_quantity'],
            'converted quantity rounds to zero' => [['K2' => '0.000001'], 'rows.2.recipe_quantity'],
            'partial recipe' => [['L5' => 'Remark only'], 'rows.5.recipe_item_code'],
            'duplicate recipe' => [['I3' => 'RICE-SAMPLE', 'J3' => 'g'], 'rows.3.recipe_item_code'],
            'conflicting details' => [['B3' => 'Different name'], 'rows.3.code'],
            'unknown category' => [['C4' => 'UNKNOWN'], 'rows.4.category_code'],
            'unknown warehouse' => [['D4' => 'UNKNOWN'], 'rows.4.warehouse_code'],
            'missing price' => [['E4' => ''], 'rows.4.price'],
            'unknown header' => [['J1' => 'recipe_units'], 'file'],
        ];
    }

    #[DataProvider('unavailableUnits')]
    public function test_import_and_menu_save_reject_unavailable_recipe_units(string $condition): void
    {
        $this->prepareInventory();
        $rice = Item::query()->where('code', 'RICE-SAMPLE')->firstOrFail();
        $grams = Unit::query()->where('symbol', 'g')->firstOrFail();
        if ($condition === 'inactive unit') {
            $grams->update(['is_active' => false]);
        } elseif ($condition === 'inactive conversion') {
            $rice->unitConversions()->where('to_unit_id', $grams->id)->update(['is_active' => false]);
        } else {
            $rice->unitConversions()->where('to_unit_id', $grams->id)->delete();
            ItemUnitConversion::query()->create([
                'item_id' => $rice->id, 'from_unit_id' => $grams->id,
                'to_unit_id' => Unit::query()->where('symbol', 'l')->value('id'), 'conversion_factor' => 10,
            ]);
        }

        $this->postJson('/api/v1/admin/menus/import', ['file' => $this->workbook()])
            ->assertUnprocessable()->assertJsonValidationErrors(['rows.2.recipe_unit']);
        $this->postJson('/api/v1/admin/menus', $this->menuPayload($rice->id, $grams->id))
            ->assertUnprocessable()->assertJsonValidationErrors(['recipes.0.unit_id']);
        $this->assertDatabaseCount('menus', 0);
        $this->assertDatabaseCount('menu_recipes', 0);
    }

    /** @return array<string, array{string}> */
    public static function unavailableUnits(): array
    {
        return [
            'inactive unit' => ['inactive unit'],
            'inactive conversion' => ['inactive conversion'],
            'disconnected conversion' => ['disconnected conversion'],
        ];
    }

    #[DataProvider('availableUnits')]
    public function test_menu_save_accepts_stock_and_both_conversion_units(string $symbol, string $expected): void
    {
        $this->prepareInventory();
        $rice = Item::query()->where('code', 'RICE-SAMPLE')->firstOrFail();
        $unit = Unit::query()->where('symbol', $symbol)->firstOrFail();

        $this->postJson('/api/v1/admin/menus', $this->menuPayload($rice->id, $unit->id))
            ->assertCreated()->assertJsonPath('data.recipes.0.base_quantity', $expected);
    }

    /** @return array<string, array{string, string}> */
    public static function availableUnits(): array
    {
        return ['stock unit' => ['kg', '1.000000'], 'from unit' => ['box', '25.000000'], 'to unit' => ['g', '0.001000']];
    }

    public function test_existing_menu_codes_are_rejected_without_overwriting(): void
    {
        $this->prepareInventory();
        $this->postJson('/api/v1/admin/menus/import', ['file' => $this->workbook()])->assertCreated();

        $this->postJson('/api/v1/admin/menus/import', ['file' => $this->workbook(['A5' => 'NEW_TEA'])])
            ->assertUnprocessable()->assertJsonValidationErrors(['rows.2.code']);
        $this->assertDatabaseCount('menus', 3);
        $this->assertDatabaseCount('menu_recipes', 3);
        $this->assertDatabaseMissing('menus', ['code' => 'NEW_TEA']);
    }

    public function test_menu_import_requires_authentication_and_import_permission(): void
    {
        $this->postJson('/api/v1/admin/menus/import')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => UserRoleEnum::InventoryAdministrator]));
        $this->postJson('/api/v1/admin/menus/import')->assertForbidden();
    }

    public function test_sample_download_and_missing_upload_validation(): void
    {
        $this->actingAs(User::factory()->withPermissions()->create(['role' => UserRoleEnum::HotelAdministrator]));
        $this->get('/api/v1/admin/menus/import-template')->assertOk()->assertDownload('menu-import-sample.xlsx');
        $this->postJson('/api/v1/admin/menus/import')->assertUnprocessable()->assertJsonValidationErrors(['file']);
    }

    private function prepareInventory(): void
    {
        $this->actingAs(User::factory()->withPermissions()->create(['role' => UserRoleEnum::HotelAdministrator]));
        $groupId = $this->postJson('/api/v1/admin/unit-groups', ['name' => 'Import Units'])->assertCreated()->json('data.id');
        foreach (['kg', 'g', 'box', 'l'] as $symbol) {
            $this->postJson('/api/v1/admin/units', ['unit_group_id' => $groupId, 'name' => $symbol, 'symbol' => $symbol])->assertCreated();
        }
        $this->postJson('/api/v1/admin/item-categories', ['name' => 'Raw Materials', 'code' => 'RAW_MATERIALS'])->assertCreated();
        $this->postJson('/api/v1/admin/items/import', ['file' => UploadedFile::fake()->createWithContent('items.xlsx', file_get_contents(resource_path('item-import-sample.xlsx')))])->assertCreated();
        $this->postJson('/api/v1/admin/menu-categories', ['name' => 'Food', 'code' => 'FOOD'])->assertCreated();
        $this->postJson('/api/v1/admin/warehouses', ['name' => 'Kitchen', 'code' => 'KITCHEN'])->assertCreated();
    }

    /** @return array{menu_category_id: int, warehouse_id: int, code: string, name: string, price: int, recipes: array<int, array{item_id: int, unit_id: int, quantity: int}>} */
    private function menuPayload(int $itemId, int $unitId): array
    {
        return [
            'menu_category_id' => MenuCategory::query()->where('code', 'FOOD')->value('id'),
            'warehouse_id' => Warehouse::query()->where('code', 'KITCHEN')->value('id'),
            'code' => 'UNIT_TEST_MENU', 'name' => 'Test Menu', 'price' => 1000,
            'recipes' => [['item_id' => $itemId, 'unit_id' => $unitId, 'quantity' => 1]],
        ];
    }

    /** @param array<string, string> $changes */
    private function workbook(array $changes = []): UploadedFile
    {
        $file = UploadedFile::fake()->createWithContent('menus.xlsx', file_get_contents(resource_path('menu-import-sample.xlsx')));
        if ($changes !== []) {
            $zip = new ZipArchive;
            $zip->open($file->getPathname());
            $xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
            foreach ($changes as $address => $value) {
                $cell = $xml->xpath('//*[local-name()="c"][@r="'.$address.'"]')[0];
                $node = dom_import_simplexml($cell);
                while ($node->firstChild !== null) {
                    $node->removeChild($node->firstChild);
                }
                $cell['t'] = 'inlineStr';
                $cell->addChild('is')->addChild('t', htmlspecialchars($value, ENT_XML1));
            }
            $zip->addFromString('xl/worksheets/sheet1.xml', $xml->asXML());
            $zip->close();
        }

        return $file;
    }
}
