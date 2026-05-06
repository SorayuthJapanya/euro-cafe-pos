<?php

namespace Tests\Feature\Category;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        return User::factory()->create(['role' => 'admin'])->createToken('test')->plainTextToken;
    }

    private function staffToken(): string
    {
        return User::factory()->create(['role' => 'staff'])->createToken('test')->plainTextToken;
    }

    /**
     * A basic feature test example.
     */
    public function test_staff_can_list_categories(): void
    {
        Category::factory()->count(3)->create();

        $this->withHeader('Authorization', 'Bearer ' . $this->staffToken())
            ->getJson('/api/v1/categories')
            ->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_can_create_category(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->postJson('/api/v1/categories', [
                'name' => 'Coffee',
                'color' => '#6F4E37'
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Coffee');
    }

    public function test_staff_cannot_create_category(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->staffToken())
            ->postJson('/api/v1/categories', [
                'name' => 'Coffee',
                'color' => '#6F4E37'
            ])
            ->assertStatus(403);
    }

    public function test_admin_can_update_category(): void
    {
        $category = Category::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->putJson("/api/v1/categories/{$category->id}", ['name' => 'Updated'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated');
    }

    public function test_cannot_delete_category_with_products(): void
    {
        $category = Category::factory()->hasProducts(1)->create();

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->deleteJson("/api/v1/categories/{$category->id}")
            ->assertStatus(409);
    }

    public function test_admin_can_delete_empty_category(): void
    {
        $category = Category::factory()->create();

        $this->withHeader('Authorization', 'Bearer ' . $this->adminToken())
            ->deleteJson("/api/v1/categories/{$category->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
