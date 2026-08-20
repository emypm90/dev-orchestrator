<?php

namespace Tests\Feature;

use Tests\TestCase;

class DevelopmentRunDemoTest extends TestCase
{
    public function test_development_run_demo_renders_the_command_flow_mock(): void
    {
        $this->get('/development-runs/demo')
            ->assertOk()
            ->assertSee('command.flow')
            ->assertSee('QA está validando')
            ->assertSee('Development Run');
    }
}
