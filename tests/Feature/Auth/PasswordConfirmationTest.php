<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_confirmation_endpoints_are_not_exposed_for_sso_accounts(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/confirm-password')->assertNotFound();
        $this->actingAs($user)->post('/confirm-password', ['password' => 'password'])->assertNotFound();
    }
}
