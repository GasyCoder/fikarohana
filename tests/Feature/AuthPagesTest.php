<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_available(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_register_page_is_available(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));
    }

    public function test_users_can_register(): void
    {
        $this->post('/register', [
            'name' => 'Aina Rakoto',
            'email' => 'aina@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/bibliotheque');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'aina@example.test',
        ]);
    }

    public function test_users_can_login_and_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'aina@example.test',
            'password' => 'password123',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect('/bibliotheque');

        $this->assertAuthenticatedAs($user);

        $this->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }
}
