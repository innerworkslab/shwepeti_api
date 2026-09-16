<?php

namespace Tests\Feature;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuManagementModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_menu_categories_with_one_endpoint(): void
    {
        $headers = $this->authHeaders();

        $created = $this->withHeaders($headers)->postJson('/api/v1/admin/menu-categories', [
            'name' => 'Main Dishes',
            'code' => 'main dishes',
            'description' => 'Lunch and dinner menus',
            'sort_order' => 10,
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.name', 'Main Dishes')
            ->assertJsonPath('data.slug', 'main-dishes')
            ->assertJsonPath('data.code', 'MAIN_DISHES')
            ->assertJsonPath('data.sort_order', 10)
            ->assertJsonPath('data.is_active', true);

        $categoryId = $created->json('data.id');

        $this->withHeaders($headers)->postJson('/api/v1/admin/menu-categories', [
            'id' => $categoryId,
            'name' => 'Signature Dishes',
            'code' => 'main dishes',
            'sort_order' => 20,
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.id', $categoryId)
            ->assertJsonPath('data.name', 'Signature Dishes')
            ->assertJsonPath('data.sort_order', 20)
            ->assertJsonPath('data.is_active', false);
    }

    public function test_it_saves_menus_with_nested_recipes_and_base_quantities(): void
    {
        $headers = $this->authHeaders();
        $setup = $this->menuSetup($headers);

        $created = $this->withHeaders($headers)->postJson('/api/v1/admin/menus', [
            'menu_category_id' => $setup['menu_category_id'],
            'name' => 'Chicken Fried Rice',
            'code' => 'chicken fried rice',
            'description' => 'House fried rice with chicken',
            'price' => 4500,
            'is_available' => true,
            'is_active' => true,
            'recipes' => [
                [
                    'item_id' => $setup['chicken_id'],
                    'unit_id' => $setup['g_id'],
                    'quantity' => 150,
                    'remark' => 'Chicken portion',
                ],
                [
                    'item_id' => $setup['rice_id'],
                    'unit_id' => $setup['g_id'],
                    'quantity' => 250,
                ],
            ],
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.name', 'Chicken Fried Rice')
            ->assertJsonPath('data.code', 'CHICKEN_FRIED_RICE')
            ->assertJsonPath('data.price', '4500.00')
            ->assertJsonCount(2, 'data.recipes')
            ->assertJsonPath('data.recipes.0.quantity', '150.000000')
            ->assertJsonPath('data.recipes.0.base_quantity', '0.150000');

        $menuId = $created->json('data.id');

        $this->withHeaders($headers)->postJson('/api/v1/admin/menus', [
            'id' => $menuId,
            'menu_category_id' => $setup['menu_category_id'],
            'name' => 'Chicken Fried Rice Updated',
            'code' => 'CHICKEN_FRIED_RICE',
            'price' => 5000,
            'is_available' => false,
            'recipes' => [
                [
                    'item_id' => $setup['rice_id'],
                    'unit_id' => $setup['g_id'],
                    'quantity' => 300,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.name', 'Chicken Fried Rice Updated')
            ->assertJsonPath('data.is_available', false)
            ->assertJsonCount(1, 'data.recipes')
            ->assertJsonPath('data.recipes.0.item_id', $setup['rice_id'])
            ->assertJsonPath('data.recipes.0.base_quantity', '0.300000');

        $this->assertDatabaseMissing('menu_recipes', [
            'menu_id' => $menuId,
            'item_id' => $setup['chicken_id'],
            'unit_id' => $setup['g_id'],
        ]);
    }

    public function test_it_toggles_menu_active_and_available_state(): void
    {
        $headers = $this->authHeaders();
        $setup = $this->menuSetup($headers);
        $menuId = $this->createMenu($headers, $setup);

        $this->withHeaders($headers)->postJson("/api/v1/admin/menus/{$menuId}/toggle-active", [
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.id', $menuId)
            ->assertJsonPath('data.is_active', false);

        $this->withHeaders($headers)->postJson("/api/v1/admin/menus/{$menuId}/toggle-available", [
            'is_available' => false,
        ])->assertOk()
            ->assertJsonPath('data.id', $menuId)
            ->assertJsonPath('data.is_available', false);
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, int>
     */
    private function menuSetup(array $headers): array
    {
        $weightGroupId = $this->withHeaders($headers)->postJson('/api/v1/admin/unit-groups', [
            'name' => 'Weight',
        ])->json('data.id');

        $kgId = $this->withHeaders($headers)->postJson('/api/v1/admin/units', [
            'unit_group_id' => $weightGroupId,
            'name' => 'kg',
            'symbol' => 'kg',
            'is_base' => true,
        ])->json('data.id');

        $gId = $this->withHeaders($headers)->postJson('/api/v1/admin/units', [
            'unit_group_id' => $weightGroupId,
            'name' => 'g',
            'symbol' => 'g',
        ])->json('data.id');

        $itemCategoryId = $this->withHeaders($headers)->postJson('/api/v1/admin/item-categories', [
            'name' => 'Raw Materials',
            'code' => 'RAW_MATERIALS',
        ])->json('data.id');

        $chickenId = $this->withHeaders($headers)->postJson('/api/v1/admin/items', [
            'item_category_id' => $itemCategoryId,
            'name' => 'Chicken',
            'code' => 'CHICKEN',
            'stock_unit_id' => $kgId,
            'item_unit_conversions' => [
                [
                    'from_unit_id' => $kgId,
                    'to_unit_id' => $gId,
                    'conversion_factor' => 1000,
                ],
            ],
        ])->json('data.id');

        $riceId = $this->withHeaders($headers)->postJson('/api/v1/admin/items', [
            'item_category_id' => $itemCategoryId,
            'name' => 'Rice',
            'code' => 'RICE',
            'stock_unit_id' => $kgId,
            'item_unit_conversions' => [
                [
                    'from_unit_id' => $kgId,
                    'to_unit_id' => $gId,
                    'conversion_factor' => 1000,
                ],
            ],
        ])->json('data.id');

        $menuCategoryId = $this->withHeaders($headers)->postJson('/api/v1/admin/menu-categories', [
            'name' => 'Food',
            'code' => 'FOOD',
        ])->json('data.id');

        return [
            'menu_category_id' => $menuCategoryId,
            'kg_id' => $kgId,
            'g_id' => $gId,
            'chicken_id' => $chickenId,
            'rice_id' => $riceId,
        ];
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, int>  $setup
     */
    private function createMenu(array $headers, array $setup): int
    {
        return $this->withHeaders($headers)->postJson('/api/v1/admin/menus', [
            'menu_category_id' => $setup['menu_category_id'],
            'name' => 'Fried Rice',
            'code' => 'FRIED_RICE',
            'price' => 3500,
            'recipes' => [
                [
                    'item_id' => $setup['rice_id'],
                    'unit_id' => $setup['g_id'],
                    'quantity' => 250,
                ],
            ],
        ])->json('data.id');
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(): array
    {
        $admin = User::factory()->create([
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => UserRoleEnum::HotelAdministrator->value,
        ]);

        $token = $this->postJson('/api/v1/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->json('data.token');

        return ['Authorization' => "Bearer {$token}"];
    }
}
