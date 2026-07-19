<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_guide_renders_safe_judge_instructions(): void
    {
        $this->get(route('demo'))
            ->assertOk()
            ->assertSee('TingHao Agent')
            ->assertSee('Track 4 Autopilot Agent')
            ->assertSee('Agent Audit')
            ->assertSee('admin@tinghao.com / password')
            ->assertSee('Phase 1 Autopilot Capability Map')
            ->assertSee('tinghao:autopilot-scan')
            ->assertSee('Review Stock Planner')
            ->assertDontSee('gula dah abis boss')
            ->assertSee(route('agent.proof'), false);
    }

    public function test_health_endpoint_returns_safe_demo_metadata(): void
    {
        config(['qwen.api_key' => null, 'qwen.mock_mode' => true]);

        $this->getJson(route('health'))
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'service' => 'TingHao Agent',
                'architecture' => 'Laravel full-stack app',
                'track' => 'Track 4 Autopilot Agent',
                'qwen_configured' => false,
                'mock_mode' => true,
                'database' => 'connected',
            ]);
    }

    public function test_agent_proof_endpoint_does_not_expose_secrets(): void
    {
        config(['qwen.api_key' => 'secret-test-key']);

        $response = $this->getJson(route('agent.proof'))
            ->assertOk()
            ->assertJson([
                'service' => 'TingHao Agent',
                'cloud_backend_target' => 'Alibaba Cloud ECS',
                'qwen_server_side' => true,
                'api_key_exposed' => false,
            ])
            ->assertJsonPath('agent_features.0', 'Smart Procurement Inbox');

        $this->assertStringNotContainsString('secret-test-key', $response->getContent());
    }
}
