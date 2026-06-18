<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_displays_read_only_sso_identity(): void
    {
        $user = User::factory()->create([
            'role' => 'employee',
            'sso_provider' => 'simulated_personal_sso',
            'sso_id' => 'employee:TEST001',
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Identitas SSO')
            ->assertSee('bersumber dari HRIS')
            ->assertSee('simulated_personal_sso')
            ->assertDontSee('Ubah Password')
            ->assertDontSee('Hapus Akun');
    }

    public function test_profile_identity_mutation_and_account_deletion_are_unavailable(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Changed Name',
            'email' => 'changed@example.com',
        ])->assertMethodNotAllowed();

        $this->actingAs($user)->delete('/profile', [
            'password' => 'password',
        ])->assertMethodNotAllowed();

        $user->refresh();
        $this->assertSame('Original Name', $user->name);
        $this->assertSame('original@example.com', $user->email);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
