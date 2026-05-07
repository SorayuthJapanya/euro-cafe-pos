<?php

namespace Tests\Feature\Product;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Symfony\Component\Console\Helper\TreeStyle;
use Tests\TestCase;

class ProductCrudTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        return User::factory()->create([
            'role' => 'admin'
        ])->createToken('test')->plainTextToken;
    }

    private function staffToken(): string
    {
        return User::factory()->create([
            'role' => 'staff'
        ])->createToken('test')->plainTextToken;
    }

    private function adminHeader(): array
    {
        return ['Authorization' => 'Bearer ' . $this->adminToken()];
    }

    private function staffHeader(): array
    {
        return ['Authorization' => 'Bearer ' . $this->staffToken()];
    }

    /**
     * A basic feature test example.
     */
    public function test_can_list_products_filtered_by_category(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(2)->create(['category_id' => $category->id]);
        Product::factory()->count(3)->create();

        $this->withHeaders($this->staffHeader())
            ->getJson("/api/v1/products?category_id={$category->id}")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_available_products(): void
    {
        Product::factory()->count(3)->create(['is_available' => true]);
        Product::factory()->count(2)->create(['is_available' => false]);

        $this->withHeaders($this->staffHeader())
            ->getJson('/api/v1/products?is_available=1')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_create_product(): void
    {
        $category = Category::factory()->create();

        $this->withHeaders($this->adminHeader())
            ->postJson('/api/v1/products', [
                'category_id' => $category->id,
                'name' => 'Americano',
                'price' => 50.00,
                'is_available' => true
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Americano');
    }

    public function test_soft_deleted_product_is_excluded_from_list(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $this->withHeaders($this->staffHeader())
            ->getJson('/api/v1/products')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_admin_can_soft_delete_product(): void
    {
        $product = Product::factory()->create();

        $this->withHeaders($this->adminHeader())
            ->deleteJson("/api/v1/products/{$product->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('products', [
            'id' => $product->id,
        ]);
    }
}
