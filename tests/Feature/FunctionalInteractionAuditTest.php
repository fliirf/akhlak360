<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Tests\TestCase;

class FunctionalInteractionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_literal_blade_route_names_exist_and_placeholder_links_are_absent(): void
    {
        foreach ($this->bladeFiles() as $file) {
            $contents = file_get_contents($file);

            $this->assertDoesNotMatchRegularExpression(
                '/href\s*=\s*["\'](?:#|javascript:void\(0\))["\']/i',
                $contents,
                "{$file} contains a placeholder link."
            );

            preg_match_all("/(?<!->)route\\(\\s*['\"]([^'\"]+)['\"]/", $contents, $matches);

            foreach (array_unique($matches[1]) as $routeName) {
                if (str_ends_with($routeName, '.')) {
                    continue;
                }

                $this->assertTrue(Route::has($routeName), "{$file} references missing route {$routeName}.");
            }
        }
    }

    public function test_plain_html_buttons_have_an_explicit_type_and_forms_have_actions(): void
    {
        foreach ($this->bladeFiles() as $file) {
            if (str_contains(str_replace('\\', '/', $file), '/views/components/')) {
                continue;
            }

            $contents = file_get_contents($file);
            preg_match_all('/<button\b([^>]*)>/i', $contents, $buttons);

            foreach ($buttons[1] as $attributes) {
                if (str_contains($attributes, '$attributes->merge')) {
                    continue;
                }

                $this->assertMatchesRegularExpression('/\btype\s*=\s*["\'][^"\']+["\']/i', $attributes, "{$file} has a button without type.");
            }

            preg_match_all('/<form\b([^>]*)>/i', $contents, $forms);

            foreach ($forms[1] as $attributes) {
                $this->assertMatchesRegularExpression('/\baction\s*=\s*["\'][^"\']+["\']/i', $attributes, "{$file} has a form without action.");
            }
        }
    }

    public function test_master_data_search_and_pagination_preserve_queries_without_duplicate_rows(): void
    {
        $admin = User::factory()->create(['role' => 'admin_hr']);

        foreach (range(1, 15) as $index) {
            Department::create([
                'name' => sprintf('Search Department %02d', $index),
                'code' => sprintf('SD%02d', $index),
            ]);
            Position::create([
                'name' => sprintf('Search Position %02d', $index),
                'level' => 'L'.$index,
            ]);
        }

        $departments = $this->actingAs($admin)
            ->get('/master-data/departments?search=Search&page=2')
            ->assertOk()
            ->assertSee('search=Search');
        $this->assertSame(5, substr_count($departments->getContent(), 'Search Department'));

        $positions = $this->actingAs($admin)
            ->get('/master-data/positions?search=Search&page=2')
            ->assertOk()
            ->assertSee('search=Search');
        $this->assertSame(5, substr_count($positions->getContent(), 'Search Position'));

        $this->actingAs($admin)
            ->get('/master-data/employees?employment_status=invalid')
            ->assertSessionHasErrors('employment_status');

        $this->actingAs($admin)
            ->get('/assessment-cycle/periods?status=invalid')
            ->assertSessionHasErrors('status');
    }

    private function bladeFiles(): array
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));
        $files = new RegexIterator($iterator, '/\.blade\.php$/i');

        return array_map(fn ($file) => $file->getPathname(), iterator_to_array($files, false));
    }
}
