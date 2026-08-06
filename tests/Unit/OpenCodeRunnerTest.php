<?php

namespace Tests\Unit;

use App\Services\Orchestrator\OpenCodeRunner;
use ReflectionClass;
use Tests\TestCase;

class OpenCodeRunnerTest extends TestCase
{
    public function test_nested_cli_runs_do_not_inherit_parent_opencode_client_session(): void
    {
        $runner = new OpenCodeRunner;
        $reflection = new ReflectionClass($runner);
        $method = $reflection->getMethod('nestedOpenCodeEnvironment');

        $environment = $method->invoke($runner);

        $this->assertSame([
            'OPENCODE_CLIENT' => false,
            'OPENCODE_SERVER_USERNAME' => false,
            'OPENCODE_SERVER_PASSWORD' => false,
        ], $environment);
    }
}
