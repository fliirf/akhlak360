<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_public_password_reset_routes_redirect_to_sso(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->get('/forgot-password')->assertRedirect('/sso/login');
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect('/sso/login');
        $this->get('/reset-password/example-token')->assertRedirect('/sso/login');
        $this->post('/reset-password', [
            'token' => 'example-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect('/sso/login');

        Notification::assertNothingSent();
    }
}
