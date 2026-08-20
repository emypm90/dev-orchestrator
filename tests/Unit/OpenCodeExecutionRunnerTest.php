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

        $this->assertSame([
            'context' => 'manual-intake',
            'planning' => 'deterministic-brief-agent',
            'slicing' => 'deterministic-slicing-agent',
            'build' => 'build',
            'qa' => 'local-qa-runner',
            'review' => 'deterministic-closure-agent',
        ], $runner->stageAgents());
    }
}
