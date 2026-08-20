<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandFlowHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_renders_the_command_flow_home_with_empty_run_state(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('command.flow')
            ->assertSee('¿Qué vamos a')
            ->assertSee('resolver hoy?')
            ->assertSee('Iniciar Development Run')
            ->assertSee('No hay Development Runs activos.')
            ->assertSee(route('tasks.index'))
            ->assertSee(route('integrations.gmail.index'))
            ->assertSee(route('settings.integrations.edit'));
    }
}
