<?php

namespace Database\Factories;

use App\Enums\SupplierTypeEnum;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'supplier_code' => 'SUP-'.fake()->unique()->numerify('######'),
            'name' => fake()->company(),
            'phone_number' => fake()->phoneNumber(),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->address(),
            'bank_account_no' => fake()->numerify('############'),
            'credit_limit_amount' => fake()->randomElement([0, 500000, 1000000, 2500000, 5000000]),
            'supplier_type' => fake()->randomElement(SupplierTypeEnum::cases())->value,
            'is_active' => true,
        ];
    }

    public function retail(): static
    {
        return $this->state(fn (): array => [
            'supplier_type' => SupplierTypeEnum::RETAIL->value,
        ]);
    }

    public function wholesale(): static
    {
        return $this->state(fn (): array => [
            'supplier_type' => SupplierTypeEnum::WHOLESALE->value,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
