<?php

namespace Tests\Unit;

use App\Services\DevelopmentRuns\OpenCodeExecutionRunner;
use Tests\TestCase;

class OpenCodeExecutionRunnerTest extends TestCase
{
    public function test_it_separates_the_orchestrator_from_the_build_worker_profile(): void
    {
        $runner = new OpenCodeExecutionRunner;
        $profile = $runner->buildProfile();

        $this->assertSame('gentle-orchestrator', $profile['orchestrator_agent']);
        $this->assertSame('build', $profile['stage_agent']);
        $this->assertSame('openai/gpt-5.5', $profile['model']);
        $this->assertSame('high', $profile['variant']);

        $planningProfile = $runner->planningProfile();
        $this->assertSame('gentle-orchestrator', $planningProfile['orchestrator_agent']);
        $this->assertSame('plan', $planningProfile['stage_agent']);
        $this->assertSame('openai/gpt-5.5', $planningProfile['model']);
        $this->assertSame('high', $planningProfile['variant']);

        $slicingProfile = $runner->slicingProfile();
        $this->assertSame('gentle-orchestrator', $slicingProfile['orchestrator_agent']);
        $this->assertSame('slices', $slicingProfile['stage_agent']);
        $this->assertSame('openai/gpt-5.5', $slicingProfile['model']);
        $this->assertSame('high', $slicingProfile['variant']);

        $reviewProfile = $runner->reviewProfile();
        $this->assertSame('gentle-orchestrator', $reviewProfile['orchestrator_agent']);
        $this->assertSame('review', $reviewProfile['stage_agent']);
        $this->assertSame('openai/gpt-5.5', $reviewProfile['model']);
        $this->assertSame('high', $reviewProfile['variant']);

        $this->assertSame([
            'context' => 'manual-intake',
            'planning' => 'plan',
            'slicing' => 'slices',
            'build' => 'build',
            'qa' => 'local-qa-runner',
            'review' => 'review',
        ], $runner->stageAgents());
    }
}
