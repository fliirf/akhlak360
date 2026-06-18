<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_root_redirects_to_login(): void
    {
        $this->get('/')
            ->assertRedirect('/sso/login');
    }

    public function test_authenticated_root_redirects_by_role(): void
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

            $this->actingAs($user)
                ->get('/')
                ->assertRedirect($path);
        }
    }
}
