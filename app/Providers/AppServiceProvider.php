<?php

namespace App\Providers;

use App\Contracts\AI\StructuredDecisionProvider;
use App\Services\AI\QwenStructuredProvider;
use App\Services\OpenAI\OpenAIClient;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StructuredDecisionProvider::class, function ($app) {
            return match (strtolower((string) config('ai.default', 'qwen'))) {
                'openai' => $app->make(OpenAIClient::class),
                default => $app->make(QwenStructuredProvider::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(UrlGenerator $url): void
    {
        if (app()->environment('production')) {
            $url->forceScheme('https');
        }
    }
}
