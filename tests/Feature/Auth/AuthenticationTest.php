<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertSee('<title>AKHLAK360 | Login</title>', false);
        $response->assertSee('AKHLAK360');
        $response->assertSee('Sistem Penilaian 360° Core Values AKHLAK');
        $response->assertSee('PT Energi Nusantara');
        $response->assertSee('Email Perusahaan');
        $response->assertSee('Kata Sandi');
        $response->assertSee('Ingat saya');
        $response->assertSee('Lupa kata sandi?');
        $response->assertSee('Masuk dengan Company SSO');
        $response->assertDontSee('Register');
        $response->assertDontSee('/register');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/employee/dashboard');
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'login',
            'module' => 'authentication',
        ]);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_remember_me_remains_functional(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => 'on',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/employee/dashboard');
        $response->assertCookie(Auth::guard('web')->getRecallerName());
    }

    public function test_users_are_redirected_to_their_role_dashboard_after_login(): void
    {
        $destinations = [
            'admin_hr' => '/admin/dashboard',
            'supervisor' => '/supervisor/dashboard',
            'employee' => '/employee/dashboard',
            'management' => '/management/dashboard',
            'it_admin' => '/it/dashboard',
        ];

        foreach ($destinations as $role => $path) {
            $user = User::factory()->create([
                'role' => $role,
            ]);

            $response = $this->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

            $response->assertRedirect($path);
            $this->post('/logout');
        }
    }

    public function test_sso_simulation_page_can_be_rendered(): void
    {
        $response = $this->get('/sso/simulation');

        $response->assertStatus(200);
        $response->assertSee('<title>AKHLAK360 | Company SSO</title>', false);
        $response->assertSee('AKHLAK360');
        $response->assertSee('Company SSO');
        $response->assertSee('Fitur Company SSO pada aplikasi ini merupakan simulasi untuk kebutuhan academic MVP.');
        $response->assertSee('Implementasi pada lingkungan produksi memerlukan integrasi dengan identity provider perusahaan menggunakan protokol seperti OIDC atau SAML.');
        $response->assertSee('Kembali ke Login');
        $response->assertSee('href="'.route('login').'"', false);
    }

    public function test_role_protected_routes_reject_other_roles(): void
    {
        $employee = User::factory()->create([
            'role' => 'employee',
        ]);

        $this->actingAs($employee)
            ->get('/admin/master-data')
            ->assertForbidden();
    }

    public function test_role_protected_routes_accept_allowed_roles(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin_hr',
        ]);

        $this->actingAs($admin)
            ->get('/admin/master-data')
            ->assertRedirect('/master-data/employees');
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Email atau kata sandi tidak sesuai.']);

        $this->assertGuest();
    }

    public function test_login_validation_messages_are_clear(): void
    {
        $this->from('/login')->post('/login', [
            'email' => '',
            'password' => '',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors([
                'email' => 'Email wajib diisi.',
                'password' => 'Kata sandi wajib diisi.',
            ]);

        $this->from('/login')->post('/login', [
            'email' => 'not-an-email',
            'password' => 'password',
        ])->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Format email tidak valid.']);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
