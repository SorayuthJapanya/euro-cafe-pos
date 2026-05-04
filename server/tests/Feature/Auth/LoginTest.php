<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */
    public function test_staff_lgin_with_valid_credentail(): void
    {
        User::factory()->create(
            [
                'email' => 'staff@mail.com',
                'password' => bcrypt('staff@2026'),
                'role' => 'staff'
            ]
        );

        $this->postJson('/api/auth/login', [
            'email' => 'starff@mail.com',
            'password' => 'staff@2026'
        ])
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role'
                    ]
                ]
            ]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'staff@mail.com',
            'password' => bcrypt('staff@2026')
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'staff@mail.com',
            'password' => 'wrong'
        ])
            ->assertStatus(401)
            ->assertJson(['status' => false]);
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/auth/login', [])->assertStatus(422);
    }
}

