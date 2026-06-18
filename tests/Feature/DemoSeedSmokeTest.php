<?php

namespace Tests\Feature;

use App\Models\AssessmentAssignment;
use App\Models\AssessmentPeriod;
use App\Models\AssessmentResult;
use App\Services\AssessmentResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeedSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_demo_users_can_login_and_reach_role_dashboards(): void
    {
        $this->seed();

        $this->from('/sso/login')
            ->post('/sso/login', [
                'identity' => 'EMP001',
                'simulation_code' => 'SSO2026',
            ])
            ->assertRedirect('/sso/login')
            ->assertSessionHasErrors('identity');
        $this->assertGuest();

        $destinations = [
            'admin_hr@example.com' => ['/admin/dashboard', 'AKH-HR01-2026'],
            'supervisor@example.com' => ['/supervisor/dashboard', 'AKH-SPV3-2026'],
            'employee@example.com' => ['/employee/dashboard', 'AKH-EMP5-2026'],
            'management@example.com' => ['/management/dashboard', 'AKH-MGT2-2026'],
            'it@example.com' => ['/it/dashboard', 'AKH-IT03-2026'],
        ];

        foreach ($destinations as $email => [$path, $code]) {
            $response = $this->post('/sso/login', [
                'identity' => $email,
                'simulation_code' => $code,
            ]);

            $response->assertRedirect($path);
            $this->get($path)->assertOk();
            $this->post('/logout')->assertRedirect('/sso/login');
        }
    }

    public function test_seeded_submissions_are_complete_and_results_survive_recalculation(): void
    {
        $this->seed();

        $activePeriod = AssessmentPeriod::open()->firstOrFail();
        $submitted = AssessmentAssignment::query()
            ->where('assessment_period_id', $activePeriod->id)
            ->submitted()
            ->withCount('responses')
            ->get();

        $this->assertNotEmpty($submitted);
        $this->assertTrue($submitted->every(fn ($assignment) => $assignment->responses_count === 18));

        $before = AssessmentResult::where('assessment_period_id', $activePeriod->id)->count();
        $recalculated = app(AssessmentResultService::class)->calculateForPeriod($activePeriod->id);
        $after = AssessmentResult::where('assessment_period_id', $activePeriod->id)->count();

        $this->assertGreaterThan(0, $before);
        $this->assertSame($before, $recalculated);
        $this->assertSame($before, $after);
        $this->assertTrue(AssessmentResult::where('assessment_period_id', $activePeriod->id)->where('final_score', '<', 3)->exists());
        $this->assertTrue(AssessmentResult::where('assessment_period_id', $activePeriod->id)->where('talent_mapping_category', 'High Potential')->exists());
    }
}
