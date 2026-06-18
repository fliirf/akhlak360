<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_endpoints_are_not_exposed_for_sso_accounts(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/verify-email')->assertNotFound();
        $this->actingAs($user)->get('/verify-email/1/example')->assertNotFound();
        $this->actingAs($user)->post('/email/verification-notification')->assertNotFound();
    }
}
