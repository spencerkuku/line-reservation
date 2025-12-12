<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $companyName = fake()->company();
        
        return [
            'name' => $companyName,
            'slug' => Str::slug($companyName) . '-' . Str::random(6),
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'logo' => null,
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
            'subscription_ends_at' => null,
            'plan' => 'basic',
            'settings' => [],
        ];
    }

    /**
     * Indicate that the tenant is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'trial_ends_at' => null,
            'subscription_ends_at' => now()->addYear(),
        ]);
    }

    /**
     * Indicate that the tenant is in trial.
     */
    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
            'subscription_ends_at' => null,
        ]);
    }

    /**
     * Indicate that the tenant is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
        ]);
    }

    /**
     * Indicate that the tenant is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Configure LINE bot credentials - creates settings after tenant is created.
     * Note: This must be called with afterCreating callback or manually create settings.
     */
    public function withLineBot(?string $accessToken = null, ?string $secret = null): static
    {
        return $this->afterCreating(function (\App\Models\Tenant $tenant) use ($accessToken, $secret) {
            \App\Models\Setting::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->create([
                'tenant_id' => $tenant->id,
                'key' => 'line_channel_access_token',
                'value' => $accessToken ?? Str::random(40),
            ]);
            \App\Models\Setting::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->create([
                'tenant_id' => $tenant->id,
                'key' => 'line_channel_secret',
                'value' => $secret ?? Str::random(32),
            ]);
        });
    }
}
