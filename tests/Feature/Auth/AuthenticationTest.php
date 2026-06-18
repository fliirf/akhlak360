<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_login_routes_redirect_to_company_sso(): void
    {
        $this->get('/login')->assertRedirect('/sso/login');
        $this->get('/sso/simulation')->assertRedirect('/sso/login');
    }

    public function test_sso_login_screen_is_the_only_public_login_form(): void
    {
        $this->get('/sso/login')
            ->assertOk()
            ->assertSee('<title>AKHLAK360 | Company SSO</title>', false)
            ->assertSee('Email Perusahaan atau Nomor Pegawai')
            ->assertSee('Kode SSO Personal')
            ->assertSee('name="identity"', false)
            ->assertSee('name="simulation_code"', false)
            ->assertSee('name="_token"', false)
            ->assertDontSee('name="password"', false)
            ->assertDontSee('name="remember"', false)
            ->assertDontSee('name="role"', false)
            ->assertDontSee('/register');
    }

    public function test_post_login_cannot_authenticate_with_a_valid_password(): void
    {
        $user = User::factory()->create(['email' => 'seeded@example.com']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/sso/login');

        $this->assertGuest();
    }

    public function test_role_protected_routes_reject_other_roles(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)
            ->get('/admin/master-data')
            ->assertForbidden();
    }

    public function test_role_protected_routes_accept_allowed_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin_hr']);

        $this->actingAs($admin)
            ->get('/admin/master-data')
            ->assertRedirect('/master-data/employees');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/sso/login');

        $this->assertGuest();
    }

    public function test_direct_logout_url_shows_safe_confirmation_instead_of_method_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/logout')
            ->assertOk()
            ->assertSee('Konfirmasi Logout')
            ->assertSee('method="POST"', false)
            ->assertSee('action="'.route('logout').'"', false);

        $this->assertAuthenticatedAs($user);
    }
}
