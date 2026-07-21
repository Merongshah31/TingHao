<?php

namespace Tests\Unit;

use App\Contracts\AI\StructuredDecisionProvider;
use App\Services\AI\QwenStructuredProvider;
use App\Services\OpenAI\OpenAIClient;
use Tests\TestCase;

class AIProviderResolutionTest extends TestCase
{
    public function test_qwen_is_resolved_by_default(): void
    {
        config(['ai.default' => 'qwen']);

        $this->assertInstanceOf(QwenStructuredProvider::class, app(StructuredDecisionProvider::class));
    }

    public function test_openai_is_resolved_from_configuration(): void
    {
        config(['ai.default' => 'openai']);

        $this->assertInstanceOf(OpenAIClient::class, app(StructuredDecisionProvider::class));
    }
}
