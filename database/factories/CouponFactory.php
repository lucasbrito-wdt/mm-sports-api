<?php

namespace Database\Factories;

use App\Domains\Marketing\Enums\CouponType;
use App\Domains\Marketing\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(8)),
            'description' => fake()->sentence(3),
            'type' => CouponType::Percentage,
            'value' => 10,
            'min_subtotal' => null,
            'max_discount' => null,
            'usage_limit' => null,
            'usage_count' => 0,
            'starts_at' => null,
            'expires_at' => null,
            'active' => true,
        ];
    }

    public function percentage(float $percent): static
    {
        return $this->state(fn () => [
            'type' => CouponType::Percentage,
            'value' => $percent,
        ]);
    }

    public function fixed(float $amount): static
    {
        return $this->state(fn () => [
            'type' => CouponType::Fixed,
            'value' => $amount,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
